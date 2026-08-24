<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled the package still binds its services, but nothing is queued
    | and nothing is submitted. Useful for local and staging environments that
    | should never talk to a search engine.
    |
    */

    'enabled' => (bool) env('INDEXNOW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | "host" is the canonical host of your site, without a scheme and without a
    | trailing slash. IndexNow only accepts URLs that belong to it, so it has to
    | match the host your canonical URLs actually use ("example.com" does not
    | cover "www.example.com").
    |
    | "key" is the IndexNow key: 8-128 characters, ASCII letters, digits and
    | dashes only. It is deliberately public - it proves you own the host, it is
    | not a credential.
    |
    | "key_location" is the absolute URL that serves the key as text/plain. When
    | left empty it defaults to https://{host}/{key}.txt, which is exactly what
    | the built-in key route serves.
    |
    */

    'host' => env('INDEXNOW_HOST'),

    'key' => env('INDEXNOW_KEY'),

    'key_location' => env('INDEXNOW_KEY_LOCATION'),

    /*
    |--------------------------------------------------------------------------
    | Search engines
    |--------------------------------------------------------------------------
    |
    | Participating engines forward submissions to each other, so the single
    | api.indexnow.org endpoint is normally enough. Add more entries only when
    | you want to notify an engine independently, for example because it was
    | verified with a different key.
    |
    | Every entry may override "key" and "key_location"; anything left out falls
    | back to the site-wide values above.
    |
    */

    'engines' => [

        'indexnow' => [
            'endpoint' => 'https://api.indexnow.org/indexnow',
        ],

        // 'bing' => ['endpoint' => 'https://www.bing.com/indexnow'],
        // 'yandex' => ['endpoint' => 'https://yandex.com/indexnow'],
        // 'seznam' => ['endpoint' => 'https://search.seznam.cz/indexnow'],
        // 'naver' => ['endpoint' => 'https://searchadvisor.naver.com/indexnow'],
        // 'yep' => ['endpoint' => 'https://indexnow.yep.com/indexnow'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pending URL store
    |--------------------------------------------------------------------------
    |
    | URLs are collected between submissions and flushed in batches.
    |
    |   cache    - fast, needs no migration, but a cache flush loses the queue
    |   database - survives deploys and cache flushes, needs the migration
    |   array    - in-memory, per request; only useful in tests
    |
    */

    'store' => env('INDEXNOW_STORE', 'cache'),

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'cache' => [
            'driver' => 'cache',
            'store' => env('INDEXNOW_CACHE_STORE'),
            'key' => env('INDEXNOW_CACHE_KEY', 'indexnow:pending'),
            'ttl' => (int) env('INDEXNOW_CACHE_TTL', 604800),
            'lock_seconds' => (int) env('INDEXNOW_CACHE_LOCK_SECONDS', 5),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('INDEXNOW_DB_CONNECTION'),
            'table' => env('INDEXNOW_DB_TABLE', 'indexnow_urls'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    |
    | Submissions go through Laravel's HTTP client, so Http::fake() works in
    | your own tests. Leave "user_agent" empty to send the package default.
    |
    */

    'http' => [
        'timeout' => (int) env('INDEXNOW_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('INDEXNOW_HTTP_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('INDEXNOW_HTTP_RETRIES', 3),
        'retry_delay' => (int) env('INDEXNOW_HTTP_RETRY_DELAY', 250),
        'user_agent' => env('INDEXNOW_HTTP_USER_AGENT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch size
    |--------------------------------------------------------------------------
    |
    | Maximum number of URLs sent in a single request. The protocol caps this at
    | 10 000; lower it if you prefer smaller payloads.
    |
    */

    'batch_size' => (int) env('INDEXNOW_BATCH_SIZE', 10000),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | When enabled, flushing dispatches SubmitQueuedUrlsJob onto your queue
    | instead of submitting inline. Leave "connection" and "queue" empty to use
    | the application defaults.
    |
    */

    'queue' => [
        'enabled' => (bool) env('INDEXNOW_QUEUE_ENABLED', true),
        'connection' => env('INDEXNOW_QUEUE_CONNECTION'),
        'queue' => env('INDEXNOW_QUEUE'),
        'tries' => (int) env('INDEXNOW_QUEUE_TRIES', 3),
        'backoff' => [60, 300, 900],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | Registers indexnow:flush on Laravel's scheduler. Requires a running
    | scheduler ("php artisan schedule:work" or the usual cron entry).
    |
    */

    'schedule' => [
        'enabled' => (bool) env('INDEXNOW_SCHEDULE_ENABLED', false),
        'cron' => env('INDEXNOW_SCHEDULE_CRON', '*/5 * * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key file route
    |--------------------------------------------------------------------------
    |
    | Serves the key at /{key}.txt so you do not have to drop a static file into
    | public/. Disable it if you serve the file yourself or if your key location
    | points somewhere else.
    |
    */

    'key_route' => [
        'enabled' => (bool) env('INDEXNOW_KEY_ROUTE_ENABLED', false),
        'middleware' => [],
    ],

];
