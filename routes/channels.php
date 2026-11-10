<?php

use App\Models\Conversation;
use App\Models\LoveGame;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn (User $user, string $id): bool => $user->id === $id);

// Former group members keep the history but no longer get the new messages
Broadcast::channel('conversations.{conversation}', fn (User $user, Conversation $conversation): bool => $conversation->hasMember($user));

Broadcast::channel('games.{game}', fn (User $user, LoveGame $game): bool => $game->hasParticipant($user));

// Profiles are visible to every signed in user, so is their online status.
Broadcast::channel('profiles.{id}', fn (User $user, string $id): bool => true);
