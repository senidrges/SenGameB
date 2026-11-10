<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\GameTemplateController as AdminGameTemplateController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\UserSuspensionController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\Auth\PhoneLookupController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisteredUserController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CallNotificationDeclineController;
use App\Http\Controllers\Api\CallSignalController;
use App\Http\Controllers\Api\CallVideoController;
use App\Http\Controllers\Api\ChatLockCodeController;
use App\Http\Controllers\Api\ContactMatchController;
use App\Http\Controllers\Api\ContactRequestController;
use App\Http\Controllers\Api\ConversationArchiveController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationDisappearingController;
use App\Http\Controllers\Api\ConversationLockController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\ConversationMuteController;
use App\Http\Controllers\Api\ConversationReadController;
use App\Http\Controllers\Api\ConversationTypingController;
use App\Http\Controllers\Api\GameActivationController;
use App\Http\Controllers\Api\GameAttachmentController;
use App\Http\Controllers\Api\GameChoiceController;
use App\Http\Controllers\Api\GameItemController;
use App\Http\Controllers\Api\GameMessageController;
use App\Http\Controllers\Api\GameMessageReactionController;
use App\Http\Controllers\Api\GameMessageVoteController;
use App\Http\Controllers\Api\GameReadController;
use App\Http\Controllers\Api\GameResetController;
use App\Http\Controllers\Api\GameTemplateController;
use App\Http\Controllers\Api\GameTypingController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupInviteController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\LiveKitWebhookController;
use App\Http\Controllers\Api\LoveGameController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MessageDeliveryController;
use App\Http\Controllers\Api\MessageForwardController;
use App\Http\Controllers\Api\MessageHideController;
use App\Http\Controllers\Api\MessageInfoController;
use App\Http\Controllers\Api\MessageReactionController;
use App\Http\Controllers\Api\MessageVoteController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfilePhotoController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UsernameAvailabilityController;
use App\Services\AuthTokens;
use Illuminate\Support\Facades\Route;

/*
| Every route is prefixed with /api/v1 (see bootstrap/app.php).
*/

// Asked by the app at launch, logged in or not
Route::get('app-version', [AppVersionController::class, 'show']);

// Signed by LiveKit Cloud (see LiveKitWebhookController)
Route::post('webhooks/livekit', LiveKitWebhookController::class);

// Signed link of the incoming call push (see IncomingCall)
Route::post('calls/{call}/notification-decline', CallNotificationDeclineController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('calls.notification-decline');

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::post('login', [SessionController::class, 'store']);
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('phone-lookup', [PhoneLookupController::class, 'store']);

    Route::post('refresh', [RefreshTokenController::class, 'store'])
        ->middleware(['auth:sanctum', 'abilities:'.AuthTokens::REFRESH_ABILITY, 'active']);
});

Route::middleware(['auth:sanctum', 'abilities:'.AuthTokens::ACCESS_ABILITY, 'active'])->group(function () {
    Route::post('auth/logout', [SessionController::class, 'destroy']);

    Route::prefix('me')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::delete('/', [AccountController::class, 'destroy']);
        Route::post('profile', [ProfileController::class, 'store']);
        Route::post('avatar', [AvatarController::class, 'store']);
        Route::delete('avatar', [AvatarController::class, 'destroy']);
        Route::delete('profile-photos/{profilePhoto}', [ProfilePhotoController::class, 'destroy']);
        Route::put('push-token', [PushTokenController::class, 'update']);
        Route::delete('push-token', [PushTokenController::class, 'destroy']);
        Route::put('presence', [PresenceController::class, 'update']);
        Route::post('deliveries', [MessageDeliveryController::class, 'store']);
        Route::put('chat-lock-code', [ChatLockCodeController::class, 'update']);
        Route::delete('chat-lock-code', [ChatLockCodeController::class, 'destroy']);
        Route::post('chat-lock-code/verify', [ChatLockCodeController::class, 'verify'])
            ->middleware('throttle:chat-lock-code');
    });

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::get('users/{user}/profile-photos', [ProfilePhotoController::class, 'index']);
    Route::get('username-availability', [UsernameAvailabilityController::class, 'show']);
    Route::post('contacts/match', [ContactMatchController::class, 'store']);

    Route::get('contact-requests', [ContactRequestController::class, 'index']);
    Route::post('contact-requests', [ContactRequestController::class, 'store'])->middleware('throttle:contact-requests');
    Route::post('contact-requests/{contactRequest}/accept', [ContactRequestController::class, 'accept']);
    Route::post('contact-requests/{contactRequest}/decline', [ContactRequestController::class, 'decline']);
    Route::delete('contact-requests/{contactRequest}', [ContactRequestController::class, 'destroy']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy']);
    Route::get('conversations/{conversation}/messages', [ConversationMessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [ConversationMessageController::class, 'store']);
    Route::post('conversations/{conversation}/read', [ConversationReadController::class, 'store']);
    Route::post('conversations/{conversation}/typing', [ConversationTypingController::class, 'store'])
        ->middleware('throttle:60,1');
    Route::post('conversations/{conversation}/attachments', [AttachmentController::class, 'store']);
    Route::post('conversations/{conversation}/archive', [ConversationArchiveController::class, 'store']);
    Route::delete('conversations/{conversation}/archive', [ConversationArchiveController::class, 'destroy']);
    Route::post('conversations/{conversation}/lock', [ConversationLockController::class, 'store']);
    Route::delete('conversations/{conversation}/lock', [ConversationLockController::class, 'destroy']);
    Route::post('conversations/{conversation}/mute', [ConversationMuteController::class, 'store']);
    Route::delete('conversations/{conversation}/mute', [ConversationMuteController::class, 'destroy']);
    Route::put('conversations/{conversation}/disappearing', [ConversationDisappearingController::class, 'update']);

    // WhatsApp-like groups
    Route::post('groups', [GroupController::class, 'store']);
    Route::get('groups/candidates', [GroupMemberController::class, 'candidates']);
    Route::patch('groups/{conversation}', [GroupController::class, 'update']);
    Route::patch('groups/{conversation}/settings', [GroupController::class, 'settings']);
    Route::post('groups/{conversation}/avatar', [GroupController::class, 'updateAvatar']);
    Route::delete('groups/{conversation}/avatar', [GroupController::class, 'destroyAvatar']);
    Route::post('groups/{conversation}/members', [GroupMemberController::class, 'store']);
    Route::delete('groups/{conversation}/members/{user}', [GroupMemberController::class, 'destroy']);
    Route::put('groups/{conversation}/members/{user}/role', [GroupMemberController::class, 'updateRole']);
    Route::post('groups/{conversation}/leave', [GroupMemberController::class, 'leave']);
    Route::get('groups/{conversation}/invite', [GroupInviteController::class, 'show']);
    Route::post('groups/{conversation}/invite/reset', [GroupInviteController::class, 'reset']);
    Route::get('groups/{conversation}/join-requests', [GroupInviteController::class, 'requests']);
    Route::post('groups/{conversation}/join-requests/{joinRequest}/approve', [GroupInviteController::class, 'approve']);
    Route::delete('groups/{conversation}/join-requests/{joinRequest}', [GroupInviteController::class, 'reject']);
    Route::get('group-invites/{code}', [GroupInviteController::class, 'preview']);
    Route::post('group-invites/{code}/join', [GroupInviteController::class, 'join'])->middleware('throttle:30,1');
    Route::delete('group-invites/{code}/request', [GroupInviteController::class, 'cancelRequest']);

    Route::post('conversations/{conversation}/calls', [CallController::class, 'store']);
    Route::get('calls/{call}', [CallController::class, 'show']);
    Route::post('calls/{call}/accept', [CallController::class, 'accept']);
    Route::post('calls/{call}/decline', [CallController::class, 'decline']);
    Route::post('calls/{call}/end', [CallController::class, 'end']);
    Route::get('calls/{call}/connection', [CallController::class, 'connection']);
    Route::post('calls/{call}/signals', [CallSignalController::class, 'store']);
    Route::post('calls/{call}/video', [CallVideoController::class, 'store']);

    Route::patch('messages/{message}', [MessageController::class, 'update']);
    Route::delete('messages/{message}', [MessageController::class, 'destroy']);
    Route::post('messages/{message}/reactions', [MessageReactionController::class, 'store']);
    Route::post('messages/{message}/forward', [MessageForwardController::class, 'store']);
    Route::post('messages/{message}/hide', [MessageHideController::class, 'store']);
    Route::post('messages/{message}/votes', [MessageVoteController::class, 'store']);
    Route::get('messages/{message}/info', [MessageInfoController::class, 'show']);

    Route::apiResource('games', LoveGameController::class)->except('update');
    Route::post('games/{game}/activation', [GameActivationController::class, 'store']);
    Route::post('games/{game}/reset', [GameResetController::class, 'store']);
    Route::put('games/{game}/items', [GameItemController::class, 'update']);
    Route::post('games/{game}/choices', [GameChoiceController::class, 'store']);
    Route::get('games/{game}/messages', [GameMessageController::class, 'index']);
    Route::post('games/{game}/messages', [GameMessageController::class, 'store']);
    Route::patch('game-messages/{gameMessage}', [GameMessageController::class, 'update']);
    Route::delete('game-messages/{gameMessage}', [GameMessageController::class, 'destroy']);
    Route::post('game-messages/{gameMessage}/reactions', [GameMessageReactionController::class, 'store']);
    Route::post('game-messages/{gameMessage}/votes', [GameMessageVoteController::class, 'store']);
    Route::post('games/{game}/attachments', [GameAttachmentController::class, 'store']);
    Route::post('games/{game}/read', [GameReadController::class, 'store']);
    Route::post('games/{game}/typing', [GameTypingController::class, 'store'])
        ->middleware('throttle:60,1');

    Route::get('game-templates', [GameTemplateController::class, 'index']);

    Route::get('blocks', [BlockController::class, 'index']);
    Route::post('blocks', [BlockController::class, 'store']);
    Route::delete('blocks/{user}', [BlockController::class, 'destroy']);

    Route::get('report-reasons', [ReportController::class, 'reasons']);
    Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports');

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        Route::apiResource('game-templates', AdminGameTemplateController::class)->except('show');
        Route::get('reports', [AdminReportController::class, 'index']);
        Route::patch('reports/{report}', [AdminReportController::class, 'update']);
        Route::post('users/{user}/suspension', [UserSuspensionController::class, 'store']);
        Route::delete('users/{user}/suspension', [UserSuspensionController::class, 'destroy']);
    });
});
