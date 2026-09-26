<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle Session Timeout
    |--------------------------------------------------------------------------
    |
    | The number of minutes a user may be idle (no activity) before being
    | automatically signed out. This is enforced both server-side (via the
    | EnforceSessionTimeout middleware) and client-side (the JS idle tracker).
    |
    | This value is INDEPENDENT of SESSION_LIFETIME, which controls how long
    | the Laravel session cookie/database entry lives. IDLE_TIMEOUT_MINUTES
    | should always be <= SESSION_LIFETIME to ensure the middleware can act
    | before PHP's own session GC removes the row.
    |
    | Recommended: SESSION_LIFETIME=120, IDLE_TIMEOUT_MINUTES=20
    |
    */

    'timeout_minutes' => (int) env('IDLE_TIMEOUT_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Warning Lead Time
    |--------------------------------------------------------------------------
    |
    | Number of seconds BEFORE idle expiry to show the warning modal.
    | The user must confirm with their password to extend the session.
    |
    */

    'warning_seconds_before' => (int) env('IDLE_WARNING_SECONDS', 120), // 2 min

];
