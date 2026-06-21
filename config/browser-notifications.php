<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prompt delay (seconds)
    |--------------------------------------------------------------------------
    |
    | How many seconds to wait after page load before showing the subscription
    | banner. Set to 0 to show immediately.
    |
    */
    'prompt_delay' => 2,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Connection and queue name for the WebPush dispatch job.
    | null = default connection/queue.
    |
    */
    'queue_connection' => null,
    'queue_name' => null,

    /*
    |--------------------------------------------------------------------------
    | Auto-cleanup dead subscriptions
    |--------------------------------------------------------------------------
    |
    | When the push endpoint returns 410 Gone or 404 Not Found, the
    | subscription is automatically deleted from the database.
    |
    */
    'cleanup_dead_subscriptions' => true,

    /*
    |--------------------------------------------------------------------------
    | Throttle (seconds)
    |--------------------------------------------------------------------------
    |
    | When multiple database notifications are created for the same user
    | within this window, only one push is sent. The push is delayed by
    | this amount and groups all notifications created in the window into
    | a single "You have N new notifications" message.
    |
    | Set to 0 to disable throttling (every notification = one push).
    |
    */
    'throttle_seconds' => 5,

    /*
    |--------------------------------------------------------------------------
    | PWA manifest
    |--------------------------------------------------------------------------
    |
    | Overrides for the generated /manifest.json. When left null, both values
    | are derived from the active Filament panel:
    |
    |   - start_url   -> the default panel's path (e.g. "/backoffice")
    |   - theme_color -> the panel's primary color, converted to a hex string
    |
    | Set them explicitly to take full control of the installed PWA.
    |
    */
    'manifest' => [
        'start_url' => null,
        'theme_color' => null,
    ],

];
