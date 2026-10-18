<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Bridges Laravel -> Node Socket.IO via Redis pub/sub, and mints the short-lived
 * HMAC handshake token the socket server verifies (see socket-server/index.js).
 */
class RealtimeService
{
    /** Publish an event to one or more rooms. Best-effort — never breaks the request. */
    public function publish(array $rooms, string $event, array $payload = []): void
    {
        if (empty($rooms)) {
            return;
        }
        try {
            Redis::connection('realtime')->publish($this->channel(), json_encode([
                'event' => $event,
                'rooms' => array_values($rooms),
                'payload' => $payload,
            ]));
        } catch (\Throwable) {
            // realtime is an enhancement — swallow if redis is unreachable
        }
    }

    /** Short-lived token authorizing a browser to join the given (public) rooms. */
    public function token(array $rooms, int $ttl = 3600): string
    {
        $body = $this->b64url(json_encode([
            'uid' => 0,
            'rooms' => array_values($rooms),
            'exp' => time() + $ttl,
        ]));
        $sig = hash_hmac('sha256', $body, $this->secret());

        return $body.'.'.$sig;
    }

    private function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function secret(): string
    {
        return (string) config('realtime.secret', '');
    }

    private function channel(): string
    {
        return (string) config('realtime.channel', 'vote.events');
    }
}
