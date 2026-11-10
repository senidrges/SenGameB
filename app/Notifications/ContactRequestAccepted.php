<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class ContactRequestAccepted extends PushNotification
{
    public function __construct(public ContactRequest $contactRequest) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        $recipient = $this->contactRequest->recipient;

        return new ExpoMessage(
            title: '🤝 Invitation acceptée',
            body: "@{$recipient->username} a accepté votre invitation. Vous pouvez maintenant discuter et jouer ensemble.",
            data: ['type' => 'contact_request_accepted', 'userId' => $recipient->id],
            channelId: 'messages',
        );
    }
}
