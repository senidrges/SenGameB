<?php

namespace App\Console\Commands;

use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * One-off migration of the LoveGames data from Supabase.
 *
 * Reads the Supabase Postgres database through the `supabase` connection
 * (SUPABASE_DB_URL), keeps every id, and copies the avatars and chat files
 * to the public disk. Running it again updates the rows already imported.
 */
#[Signature('supabase:import {--skip-files : Keep pointing to the Supabase storage URLs instead of copying the files}')]
#[Description('Import users, conversations, messages and games from Supabase')]
class ImportFromSupabase extends Command
{
    private const CHUNK = 500;

    private ConnectionInterface $source;

    /**
     * Normalized phone numbers already given to an imported user.
     *
     * @var array<string, true>
     */
    private array $usedPhoneNumbers = [];

    private int $copiedFiles = 0;

    private int $failedFiles = 0;

    public function handle(): int
    {
        if (config('database.connections.supabase.url') === null) {
            $this->components->error('Set SUPABASE_DB_URL in .env (Supabase dashboard > Connect > Session pooler).');

            return self::FAILURE;
        }

        $this->source = DB::connection('supabase');

        Schema::withoutForeignKeyConstraints(function (): void {
            $this->components->task('Users and profiles', fn () => $this->importUsers());
            $this->components->task('Conversations', fn () => $this->importConversations());
            $this->components->task('Messages', fn () => $this->importMessages());
            $this->components->task('Games', fn () => $this->importGames());
            $this->components->task('Game messages', fn () => $this->importGameMessages());
            $this->components->task('Game templates', fn () => $this->importGameTemplates());
        });

        $isComplete = $this->reportCounts();

        if ($this->failedFiles > 0) {
            $this->components->warn("{$this->failedFiles} file(s) could not be copied and still point to Supabase (see warnings above).");
        }

        if (! $isComplete) {
            $this->components->error('Some rows are missing: check the table above before going live.');

            return self::FAILURE;
        }

        $this->components->info("Import finished ({$this->copiedFiles} file(s) copied). Existing passwords keep working; users only need to log in again.");

        return self::SUCCESS;
    }

    /**
     * Compare the number of rows in Supabase with the imported ones.
     */
    private function reportCounts(): bool
    {
        $tables = [
            'users' => [$this->source->table('auth.users')->whereNotNull('email'), 'users'],
            'conversations' => [$this->source->table('conversations'), 'conversations'],
            'conversation_participants' => [$this->source->table('conversation_participants'), 'conversation_participants'],
            'messages' => [$this->source->table('messages'), 'messages'],
            'love_games' => [$this->source->table('love_games'), 'love_games'],
            'game_participants' => [$this->source->table('game_participants'), 'game_participants'],
            'game_items' => [$this->source->table('game_items'), 'game_items'],
            'game_choices' => [$this->source->table('game_choices'), 'game_choices'],
            'game_messages' => [$this->source->table('game_messages'), 'game_messages'],
            'game_templates' => [$this->source->table('game_templates'), 'game_templates'],
        ];

        $rows = [];
        $isComplete = true;

        foreach ($tables as $name => [$sourceQuery, $targetTable]) {
            $sourceCount = $sourceQuery->count();
            $targetCount = DB::table($targetTable)->count();
            $isComplete = $isComplete && $targetCount >= $sourceCount;

            $rows[] = [$name, $sourceCount, $targetCount, $targetCount >= $sourceCount ? 'OK' : 'MISSING'];
        }

        $this->table(['Table', 'Supabase', 'Imported', ''], $rows);

        return $isComplete;
    }

    private function importUsers(): void
    {
        $this->source->table('auth.users')
            ->leftJoin('public.profiles', 'profiles.id', '=', 'users.id')
            ->whereNotNull('users.email')
            ->select([
                'users.id', 'users.email', 'users.encrypted_password', 'users.email_confirmed_at',
                'users.created_at as account_created_at',
                'profiles.username', 'profiles.first_name', 'profiles.last_name', 'profiles.phone_number',
                'profiles.avatar_url', 'profiles.status', 'profiles.is_online', 'profiles.last_seen',
                'profiles.biometrics_enabled', 'profiles.first_login_completed', 'profiles.is_admin',
                'profiles.push_token', 'profiles.created_at',
            ])
            ->orderBy('users.created_at')
            ->chunk(self::CHUNK, function ($users): void {
                $rows = $users->map(fn (stdClass $user): array => [
                    'id' => $user->id,
                    'email' => mb_strtolower($user->email),
                    'email_verified_at' => $this->timestamp($user->email_confirmed_at),
                    'password' => $this->laravelPasswordHash($user->encrypted_password),
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone_number' => $this->uniquePhoneNumber($user->phone_number, $user->email),
                    'avatar_path' => $this->copyFile($user->avatar_url),
                    'status' => $user->status ?? 'Hey, I use LoveGames!',
                    'is_online' => false,
                    'last_seen_at' => $this->timestamp($user->last_seen),
                    'biometrics_enabled' => (bool) $user->biometrics_enabled,
                    'first_login_completed' => (bool) $user->first_login_completed,
                    'is_admin' => (bool) $user->is_admin,
                    'push_token' => $user->push_token,
                    'created_at' => $this->timestamp($user->created_at ?? $user->account_created_at),
                    'updated_at' => now(),
                ])->all();

                DB::table('users')->upsert($rows, ['id']);
            });
    }

    private function importConversations(): void
    {
        $participantsByConversation = $this->source->table('conversation_participants')
            ->get()
            ->groupBy('conversation_id');

        $usedDirectKeys = [];

        // Most recent first, so the active conversation of a pair keeps the direct key.
        $this->source->table('conversations')
            ->orderByDesc('updated_at')
            ->chunk(self::CHUNK, function ($conversations) use ($participantsByConversation, &$usedDirectKeys): void {
                $rows = [];
                $participantRows = [];

                foreach ($conversations as $conversation) {
                    $participants = $participantsByConversation->get($conversation->id, collect());
                    $directKey = null;

                    if ($participants->count() === 2) {
                        $directKey = $participants->pluck('user_id')->sort()->implode(':');

                        if (isset($usedDirectKeys[$directKey])) {
                            $directKey = null;
                        } else {
                            $usedDirectKeys[$directKey] = true;
                        }
                    }

                    $rows[] = [
                        'id' => $conversation->id,
                        'direct_key' => $directKey,
                        'created_at' => $this->timestamp($conversation->created_at),
                        'updated_at' => $this->timestamp($conversation->updated_at),
                    ];

                    foreach ($participants as $participant) {
                        $participantRows[] = [
                            'conversation_id' => $participant->conversation_id,
                            'user_id' => $participant->user_id,
                            'joined_at' => $this->timestamp($participant->joined_at) ?? now(),
                        ];
                    }
                }

                DB::table('conversations')->upsert($rows, ['id']);
                DB::table('conversation_participants')->insertOrIgnore($participantRows);
            });
    }

    private function importMessages(): void
    {
        $this->source->table('messages')
            ->orderBy('created_at')
            ->chunk(self::CHUNK, function ($messages): void {
                $rows = $messages->map(fn (stdClass $message): array => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'reply_to_id' => $message->reply_to_id,
                    'content' => $message->is_deleted ? null : $message->content,
                    'message_type' => $message->message_type ?? 'text',
                    'file_path' => $message->is_deleted ? null : $this->copyFile($message->file_url),
                    'file_name' => $message->file_name,
                    'file_size' => $message->file_size,
                    'reactions' => $this->json($message->reactions),
                    'is_read' => (bool) $message->is_read,
                    'is_deleted' => (bool) $message->is_deleted,
                    'edited_at' => $this->timestamp($message->edited_at),
                    'created_at' => $this->timestamp($message->created_at),
                    'updated_at' => $this->timestamp($message->edited_at ?? $message->created_at),
                ])->all();

                DB::table('messages')->upsert($rows, ['id']);
            });
    }

    private function importGames(): void
    {
        $this->source->table('love_games')
            ->orderBy('created_at')
            ->chunk(self::CHUNK, function ($games): void {
                DB::table('love_games')->upsert($games->map(fn (stdClass $game): array => [
                    'id' => $game->id,
                    'conversation_id' => $game->conversation_id,
                    'organizer_id' => $game->organizer_id,
                    'title' => $game->title,
                    'status' => $game->status ?? 'waiting',
                    'created_at' => $this->timestamp($game->created_at),
                    'updated_at' => $this->timestamp($game->updated_at),
                ])->all(), ['id']);
            });

        $this->source->table('game_participants')->orderBy('game_id')->chunk(self::CHUNK, function ($participants): void {
            DB::table('game_participants')->insertOrIgnore($participants->map(fn (stdClass $participant): array => [
                'game_id' => $participant->game_id,
                'user_id' => $participant->user_id,
                'role' => $participant->role ?? 'player',
                'joined_at' => $this->timestamp($participant->joined_at) ?? now(),
            ])->all());
        });

        $this->source->table('game_items')->orderBy('game_id')->chunk(self::CHUNK, function ($items): void {
            DB::table('game_items')->upsert($items->map(fn (stdClass $item): array => [
                'id' => $item->id,
                'game_id' => $item->game_id,
                'number' => $item->number,
                'content' => $item->content,
                'created_at' => $this->timestamp($item->created_at),
                'updated_at' => $this->timestamp($item->created_at),
            ])->all(), ['id']);
        });

        $this->source->table('game_choices')->orderBy('game_id')->chunk(self::CHUNK, function ($choices): void {
            DB::table('game_choices')->upsert($choices->map(fn (stdClass $choice): array => [
                'id' => (string) Str::uuid(),
                'game_id' => $choice->game_id,
                'user_id' => $choice->user_id,
                'choice_number' => $choice->choice_number,
                'chosen_at' => $this->timestamp($choice->chosen_at) ?? now(),
            ])->all(), ['game_id', 'user_id'], ['choice_number', 'chosen_at']);
        });
    }

    private function importGameMessages(): void
    {
        $this->source->table('game_messages')
            ->orderBy('created_at')
            ->chunk(self::CHUNK, function ($messages): void {
                DB::table('game_messages')->upsert($messages->map(fn (stdClass $message): array => [
                    'id' => $message->id,
                    'game_id' => $message->game_id,
                    'sender_id' => $message->sender_id,
                    'reply_to_id' => $message->reply_to_id,
                    'content' => $message->is_deleted ? '' : $message->content,
                    'reactions' => $this->json($message->reactions),
                    'is_deleted' => (bool) $message->is_deleted,
                    'edited_at' => $this->timestamp($message->edited_at),
                    'created_at' => $this->timestamp($message->created_at),
                    'updated_at' => $this->timestamp($message->edited_at ?? $message->created_at),
                ])->all(), ['id']);
            });
    }

    private function importGameTemplates(): void
    {
        $this->source->table('game_templates')->orderBy('created_at')->chunk(self::CHUNK, function ($templates): void {
            DB::table('game_templates')->upsert($templates->map(fn (stdClass $template): array => [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'category' => $template->category,
                'items' => $this->json($template->items) ?? '[]',
                'is_published' => (bool) $template->is_published,
                'created_at' => $this->timestamp($template->created_at),
                'updated_at' => $this->timestamp($template->updated_at),
            ])->all(), ['id']);
        });
    }

    /**
     * Supabase (GoTrue) hashes passwords with bcrypt under the "$2a$" prefix.
     * It is the same algorithm as PHP's "$2y$", which Laravel requires.
     * Accounts without a password get an unusable one and must reset it.
     */
    private function laravelPasswordHash(?string $hash): string
    {
        if ($hash === null || $hash === '') {
            return bcrypt(Str::random(40));
        }

        return preg_replace('/^\$2[ab]\$/', '\$2y\$', $hash);
    }

    /**
     * Phone numbers are unique once normalized: a duplicate is dropped.
     */
    private function uniquePhoneNumber(?string $phoneNumber, string $email): ?string
    {
        if ($phoneNumber === null || $phoneNumber === '') {
            return null;
        }

        $normalized = PhoneNumber::normalize($phoneNumber);

        if (isset($this->usedPhoneNumbers[$normalized])) {
            $this->components->warn("Duplicate phone number {$normalized} dropped for {$email}.");

            return null;
        }

        $this->usedPhoneNumbers[$normalized] = true;

        return $normalized;
    }

    /**
     * Copy a file from a public Supabase storage bucket to the public disk,
     * under the same "bucket/path". Returns the new path, or the original URL
     * when the file is not copied.
     */
    private function copyFile(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! preg_match('#/storage/v1/object/public/([^/]+)/([^?]+)#', $url, $matches)) {
            return $url;
        }

        $path = $matches[1].'/'.rawurldecode($matches[2]);

        if ($this->option('skip-files') || str_contains($path, '..')) {
            return $url;
        }

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(60)->retry(2, 1000)->get($url);
            Storage::disk('public')->put($path, $response->body());
            $this->copiedFiles++;

            return $path;
        } catch (Throwable $exception) {
            $this->failedFiles++;
            $this->components->warn("Could not copy {$url}: {$exception->getMessage()}");

            return $url;
        }
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    /**
     * JSON value to store, null for an empty object or array.
     */
    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return empty($decoded) ? null : json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
