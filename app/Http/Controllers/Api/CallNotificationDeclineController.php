<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinishCall;
use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\Response;

/**
 * "Refuser" on the incoming call screen shown over the lock screen. The
 * Android app answers that button natively, without the user's session: the
 * signed, short-lived link sent in the push (see IncomingCall) stands for it.
 */
class CallNotificationDeclineController extends Controller
{
    public function __invoke(Call $call, FinishCall $finishCall): Response
    {
        if ($call->status === CallStatus::Ringing) {
            $finishCall->handle($call, CallStatus::Declined);
        }

        return response()->noContent();
    }
}
