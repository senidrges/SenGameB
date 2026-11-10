<?php

namespace App\Actions;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

/**
 * Disappearing messages, like WhatsApp: once their time is up they are
 * really erased, with their files (unless a forwarded copy still uses them).
 * They are already hidden by Message::visibleTo() meanwhile.
 *
 * Runs on the requests listing conversations and messages (the shared host
 * has no scheduler) and every few minutes where one is set up.
 */
class PurgeExpiredMessages
{
    private const BATCH = 500;

    public function handle(): int
    {
        $expired = Message::query()->expired()->limit(self::BATCH)->get(['id', 'file_path', 'payload']);

        if ($expired->isEmpty()) {
            return 0;
        }

        $files = $expired
            ->flatMap(fn (Message $message) => array_filter([$message->file_path, $message->payload['preview_path'] ?? null]))
            ->unique()
            ->values();

        Message::query()->whereKey($expired->modelKeys())->delete();

        // A copy forwarded to a chat without a timer keeps its file
        $stillUsed = Message::query()->whereIn('file_path', $files)->pluck('file_path')->all();
        $orphans = $files->reject(fn (string $path) => in_array($path, $stillUsed, true) || str_starts_with($path, 'http'));

        if ($orphans->isNotEmpty()) {
            Storage::disk('public')->delete($orphans->all());
        }

        return $expired->count();
    }
}
