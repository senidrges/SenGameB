<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinishCall;
use App\Actions\GroupCalls;
use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Refuser" on the incoming call screen shown over the lock screen. The
 * Android app answers that button natively, without the user's session: the
 * signed, short-lived link sent in the push (see IncomingCall) stands for it.
 */
class CallNotificationDeclineController extends Controller
{
    public function __invoke(Request $request, Call $call, FinishCall $finishCall, GroupCalls $groupCalls): Response
    {
        // Group call: the signed link names the member who declines
        if ($call->is_group) {
            $user = User::find($request->query('user'));
            if ($user !== null) {
                $groupCalls->decline($call, $user);
            }

            return response()->noContent();
        }

        if ($call->status === CallStatus::Ringing) {
            $finishCall->handle($call, CallStatus::Declined);
        }

        return response()->noContent();
    }
}
