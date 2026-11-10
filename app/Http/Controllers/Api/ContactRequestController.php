<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactRequestStatus;
use App\Events\ContactRequestChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactRequestResource;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestAccepted;
use App\Notifications\ContactRequestReceived;
use App\Support\Realtime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Invitations to talk with someone whose number the user does not have,
 * like friend requests on Facebook. A declined invitation can be sent
 * again, but the presentation message only once per person.
 */
class ContactRequestController extends Controller
{
    /**
     * Pending invitations the user received or sent, most recent first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $activeProfile = fn (Builder $query) => $query->whereNotNull('username')->whereNull('suspended_at');

        $requests = ContactRequest::query()
            ->pending()
            ->where(fn (Builder $query) => $query->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->whereHas('sender', $activeProfile)
            ->whereHas('recipient', $activeProfile)
            ->with(['sender', 'recipient'])
            ->latest()
            ->get();

        return ContactRequestResource::collection($requests);
    }

    /**
     * Invite a user, with an optional presentation message. When that user
     * already invited the sender, their invitation is accepted instead.
     */
    public function store(Request $request): JsonResponse
    {
        $sender = $request->user();

        $validated = $request->validate([
            'user_id' => [
                'required',
                'uuid',
                Rule::notIn([$sender->id]),
                Rule::exists('users', 'id')->whereNotNull('username')->whereNull('suspended_at'),
            ],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $recipient = User::findOrFail($validated['user_id']);
        $message = trim((string) ($validated['message'] ?? '')) ?: null;

        if ($sender->hasBlockWith($recipient)) {
            throw ValidationException::withMessages(['user_id' => 'Utilisateur introuvable.']);
        }

        if ($sender->canReach($recipient)) {
            throw ValidationException::withMessages(['user_id' => 'Vous pouvez déjà discuter avec cette personne.']);
        }

        $result = DB::transaction(function () use ($sender, $recipient, $message): ContactRequest {
            // One request at a time per pair: two taps or two crossed invitations wait for each other
            User::query()->whereKey([$sender->id, $recipient->id])->lockForUpdate()->get();

            $pending = ContactRequest::query()->between($sender, $recipient)->pending()->first();

            if ($pending?->recipient_id === $sender->id) {
                $pending->update(['status' => ContactRequestStatus::Accepted, 'responded_at' => now()]);

                return $pending;
            }

            if ($pending !== null) {
                throw ValidationException::withMessages(['user_id' => 'Vous avez déjà envoyé une invitation à cette personne.']);
            }

            $introAlreadySent = ContactRequest::query()
                ->where('sender_id', $sender->id)
                ->where('recipient_id', $recipient->id)
                ->whereNotNull('message')
                ->exists();

            if ($message !== null && $introAlreadySent) {
                throw ValidationException::withMessages(['message' => 'Vous avez déjà envoyé un message de présentation à cette personne.']);
            }

            return ContactRequest::create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'message' => $message,
            ]);
        });

        $result->load(['sender', 'recipient']);
        Realtime::send(new ContactRequestChanged($result));

        if ($result->status === ContactRequestStatus::Accepted) {
            $result->sender->notify(new ContactRequestAccepted($result));

            return ContactRequestResource::make($result)->response();
        }

        $recipient->notify(new ContactRequestReceived($result));

        return ContactRequestResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * The invited user accepts: from now on they can talk and play together.
     */
    public function accept(ContactRequest $contactRequest): ContactRequestResource
    {
        Gate::authorize('respond', $contactRequest);

        $this->respond($contactRequest, ContactRequestStatus::Accepted);
        $contactRequest->sender->notify(new ContactRequestAccepted($contactRequest));

        return ContactRequestResource::make($contactRequest);
    }

    /**
     * The invited user declines. The sender is not told, and may invite again.
     */
    public function decline(ContactRequest $contactRequest): ContactRequestResource
    {
        Gate::authorize('respond', $contactRequest);

        $this->respond($contactRequest, ContactRequestStatus::Declined);

        return ContactRequestResource::make($contactRequest);
    }

    /**
     * The sender cancels a pending invitation. Its presentation message
     * still counts: it cannot be sent again.
     */
    public function destroy(ContactRequest $contactRequest): Response
    {
        Gate::authorize('cancel', $contactRequest);

        $this->respond($contactRequest, ContactRequestStatus::Cancelled);

        return response()->noContent();
    }

    private function respond(ContactRequest $contactRequest, ContactRequestStatus $status): void
    {
        if (! $contactRequest->isPending()) {
            throw ValidationException::withMessages(['status' => "Cette invitation n'est plus en attente."]);
        }

        $contactRequest->update(['status' => $status, 'responded_at' => now()]);
        $contactRequest->load(['sender', 'recipient']);

        Realtime::send(new ContactRequestChanged($contactRequest));
    }
}
