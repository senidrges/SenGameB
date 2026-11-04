<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinishCall;
use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Services\Calls\HmacJwt;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Events sent by LiveKit Cloud. When a call room closes (both phones left,
 * even without hanging up properly), the call ends: its duration then
 * counts exactly in the free quota.
 */
class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, FinishCall $finishCall): Response
    {
        $config = config('services.livekit');
        $body = $request->getContent();
        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $claims = blank($config['api_secret']) ? null : HmacJwt::decode($token, $config['api_secret']);

        abort_if(
            $claims === null
                || ($claims['iss'] ?? null) !== $config['api_key']
                || ! hash_equals(base64_encode(hash('sha256', $body, true)), (string) ($claims['sha256'] ?? '')),
            401,
        );

        if ($request->json('event') === 'room_finished') {
            $call = Call::find($request->json('room.name'));

            if ($call !== null && ! $call->status->isFinished()) {
                $finishCall->handle($call, $call->status === CallStatus::Ongoing ? CallStatus::Ended : CallStatus::Missed);
            }
        }

        return response()->noContent();
    }
}
