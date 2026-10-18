<?php

return [
    // Shared HMAC secret with the Node socket server (must match SOCKET_SECRET there).
    'secret' => env('SOCKET_SECRET', ''),

    // Redis pub/sub channel the Node socket server subscribes to.
    'channel' => env('SOCKET_CHANNEL', 'vote.events'),

    // Lifetime (seconds) of browser handshake tokens.
    'token_ttl' => (int) env('SOCKET_TOKEN_TTL', 3600),
];
