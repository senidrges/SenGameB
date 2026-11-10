<?php

namespace App\Support;

use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Where the viewer stands with other users, for the search results and the
 * profiles: whether they can talk, or the invitation waiting between them.
 */
class ContactRelationship
{
    public const CONNECTED = 'connected';

    public const REQUEST_SENT = 'request_sent';

    public const REQUEST_RECEIVED = 'request_received';

    public const NONE = 'none';

    /**
     * @param  Collection<int, User>  $users
     * @return array<string, array{status: string, request_id: string|null, can_send_intro: bool}>
     */
    public static function forViewer(User $viewer, Collection $users): array
    {
        $ids = $users->modelKeys();

        if ($ids === []) {
            return [];
        }

        $reachable = array_flip($viewer->reachableIdsAmong($ids));

        $pending = ContactRequest::query()
            ->pending()
            ->where(fn ($query) => $query
                ->where(fn ($sent) => $sent->where('sender_id', $viewer->id)->whereIn('recipient_id', $ids))
                ->orWhere(fn ($received) => $received->where('recipient_id', $viewer->id)->whereIn('sender_id', $ids)))
            ->get(['id', 'sender_id', 'recipient_id']);

        $introSentTo = array_flip(ContactRequest::query()
            ->where('sender_id', $viewer->id)
            ->whereIn('recipient_id', $ids)
            ->whereNotNull('message')
            ->pluck('recipient_id')
            ->all());

        return collect($ids)->mapWithKeys(function (string $id) use ($viewer, $reachable, $pending, $introSentTo) {
            $sent = $pending->first(fn (ContactRequest $request) => $request->sender_id === $viewer->id && $request->recipient_id === $id);
            $received = $pending->first(fn (ContactRequest $request) => $request->sender_id === $id);

            $status = match (true) {
                isset($reachable[$id]) => self::CONNECTED,
                $sent !== null => self::REQUEST_SENT,
                $received !== null => self::REQUEST_RECEIVED,
                default => self::NONE,
            };

            return [$id => [
                'status' => $status,
                'request_id' => match ($status) {
                    self::REQUEST_SENT => $sent->id,
                    self::REQUEST_RECEIVED => $received->id,
                    default => null,
                },
                'can_send_intro' => ! isset($introSentTo[$id]),
            ]];
        })->all();
    }
}
