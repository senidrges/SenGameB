<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;
use Illuminate\Support\Str;

class ContactRequestReceived extends PushNotification
{
    public function __construct(public ContactRequest $contactRequest) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        $sender = $this->contactRequest->sender;
        $name = trim("{$sender->first_name} {$sender->last_name}") ?: $sender->username;

        return new ExpoMessage(
            title: '👋 Nouvelle invitation',
            body: $this->contactRequest->message === null
                ? "{$name} (@{$sender->username}) souhaite discuter avec vous."
                : "{$name} : ".Str::limit($this->contactRequest->message, 120),
            data: ['type' => 'contact_request', 'requestId' => $this->contactRequest->id],
            channelId: 'messages',
        );
    }
}
