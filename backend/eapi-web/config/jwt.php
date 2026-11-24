<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT (access tokens issued by POST /auth/tokens)
    |--------------------------------------------------------------------------
    |
    | Tokens are signed with HS256. The secret only lives in the environment (.env), and the
    | token lifetime is in seconds (also returned to the browser as "expiresIn").
    |
    */

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL_SECONDS', 3600),

];
