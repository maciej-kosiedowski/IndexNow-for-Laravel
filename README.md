# IndexNow for Laravel

[![CI](https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/actions/workflows/ci.yml)
[![Security](https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/actions/workflows/security.yml/badge.svg?branch=master)](https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/actions/workflows/security.yml)
[![Latest stable version](https://img.shields.io/packagist/v/slimad/indexnow-laravel.svg)](https://packagist.org/packages/slimad/indexnow-laravel)
[![PHP version](https://img.shields.io/packagist/dependency-v/slimad/indexnow-laravel/php.svg)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Laravel integration for [IndexNow](https://www.indexnow.org/documentation): tell Bing, Yandex,
Seznam, Naver and Yep about new, updated and deleted URLs the moment your content changes,
instead of waiting for the next crawl.

It wraps the framework-agnostic
[`slimad/indexnow`](https://github.com/maciej-kosiedowski/IndexNow) package and adds everything
Laravel-shaped: configuration, container bindings, a cache- or database-backed pending queue, a
queued job, four Artisan commands, a scheduler binding, events, a key-file route and an Eloquent
trait.

## How it works

URLs are **queued** as they change and **submitted in batches**. Nothing talks to a search engine
during a web request.

```
Model saved / event listener / Artisan
                │
                ▼
        IndexNow::submit()                  ← cheap: one write to the pending store
                │
                ▼
   pending store (cache | database)
                │
   scheduler ───┤
                ▼
       indexnow:flush  ──►  SubmitQueuedUrlsJob  ──►  Laravel HTTP client  ──►  endpoints
```

URLs stay in the store until every configured engine has accepted them, so a failed submission is
retried on the next run instead of being lost.

## Requirements

* PHP 8.2+
* Laravel 11, 12 or 13

## Installation

```bash
composer require slimad/indexnow-laravel
```

The service provider and the `IndexNow` facade are registered automatically.

Publish the configuration:

```bash
php artisan vendor:publish --tag=indexnow-config
```

Generate a key and see exactly what to put in `.env`:

```bash
php artisan indexnow:key
```

```env
INDEXNOW_HOST=example.com
INDEXNOW_KEY=8f14e45fceea167a5a36dedd4bea2543
```

Finally, make the key readable as `text/plain` at `https://example.com/{key}.txt`. Either drop the
file into `public/`, or let the package serve it:

```env
INDEXNOW_KEY_ROUTE_ENABLED=true
```

Check everything at once:

```bash
php artisan indexnow:status
```

```
+------------------------+----------------------------------------------------------+
| Setting                | Value                                                    |
+------------------------+----------------------------------------------------------+
| Enabled                | yes                                                      |
| Store                  | cache                                                    |
| Pending URLs           | 12                                                       |
| Queue                  | yes                                                      |
| Host                   | example.com                                              |
| Engine: indexnow       | https://api.indexnow.org/indexnow                        |
| Key location: indexnow | https://example.com/8f14e45fceea167a5a36dedd4bea2543.txt  |
+------------------------+----------------------------------------------------------+
```

## Queueing URLs

### From anywhere

```php
use SlimAD\IndexNow\Laravel\Facades\IndexNow;

IndexNow::submit('https://example.com/products/42');

IndexNow::submit(
    'https://example.com/products/42',
    'https://example.com/categories/shoes',
);

IndexNow::submitMany($urls);   // any iterable of strings or SlimAD\IndexNow\ValueObject\Url
```

`submit()` returns the number of distinct URLs that were queued, and throws
`SlimAD\IndexNow\Exception\InvalidUrlException` if a value is not an absolute `http`/`https` URL.

### From an Eloquent model

Implement `ProvidesIndexNowUrls` and use the `SubmitsToIndexNow` trait; the model's URLs are queued
on every `saved` and `deleted` event.

```php
use Illuminate\Database\Eloquent\Model;
use SlimAD\IndexNow\Laravel\Concerns\SubmitsToIndexNow;
use SlimAD\IndexNow\Laravel\Contracts\ProvidesIndexNowUrls;

final class Product extends Model implements ProvidesIndexNowUrls
{
    use SubmitsToIndexNow;

    public function indexNowUrls(): iterable
    {
        if (! $this->is_published) {
            return [];          // drafts are not worth a crawl
        }

        return [
            route('products.show', $this),
            route('categories.show', $this->category),
        ];
    }
}
```

Return an empty iterable to skip a model. `$model->submitToIndexNow()` queues the same URLs on
demand.

### From the command line

```bash
php artisan indexnow:submit https://example.com/a https://example.com/b
php artisan indexnow:submit https://example.com/a --flush
```

## Submitting

### Scheduled (recommended)

```env
INDEXNOW_SCHEDULE_ENABLED=true
INDEXNOW_SCHEDULE_CRON="*/5 * * * *"
```

The package registers `indexnow:flush` on Laravel's scheduler with `withoutOverlapping()`. You need
a running scheduler (`php artisan schedule:work`, or the usual `schedule:run` cron entry).

### Manually

```bash
php artisan indexnow:flush          # dispatches a queued job when queueing is on
php artisan indexnow:flush --sync   # always submits inline
```

### Programmatically

```php
$result = IndexNow::flush();        // submits inline, returns a SubmitJobResult

$result->submittedUrls;             // how many URLs were in the batch
$result->discardedUrls;             // queued URLs that did not belong to the configured host
$result->failures;                  // list<SubmitFailedException>, one per failing engine
$result->isSuccess();

IndexNow::dispatchFlush();          // hands it to a queue worker, false when queueing is off
IndexNow::pending();                // how many URLs are waiting
IndexNow::clear();                  // empty the queue without submitting
```

## Configuration

Every setting has an environment variable, so the published config file is optional.

| Key | Env | Default | What it does |
| --- | --- | --- | --- |
| `enabled` | `INDEXNOW_ENABLED` | `true` | Master switch. When off, nothing is queued and nothing is submitted. |
| `host` | `INDEXNOW_HOST` | — | Your canonical host. IndexNow only accepts URLs that belong to it. |
| `key` | `INDEXNOW_KEY` | — | The IndexNow key, 8–128 ASCII letters, digits and dashes. |
| `key_location` | `INDEXNOW_KEY_LOCATION` | `https://{host}/{key}.txt` | Where the key file lives. |
| `engines` | — | `api.indexnow.org` | Endpoints to notify; each may override `key` and `key_location`. |
| `store` | `INDEXNOW_STORE` | `cache` | Which pending store to use: `cache`, `database` or `array`. |
| `batch_size` | `INDEXNOW_BATCH_SIZE` | `10000` | URLs per request; the protocol caps this at 10 000. |
| `http.timeout` | `INDEXNOW_HTTP_TIMEOUT` | `10` | Request timeout in seconds. |
| `http.connect_timeout` | `INDEXNOW_HTTP_CONNECT_TIMEOUT` | `5` | Connection timeout in seconds. |
| `http.retries` | `INDEXNOW_HTTP_RETRIES` | `3` | Attempts per request. |
| `http.retry_delay` | `INDEXNOW_HTTP_RETRY_DELAY` | `250` | Milliseconds between attempts. |
| `http.user_agent` | `INDEXNOW_HTTP_USER_AGENT` | package default | Identify your own application. |
| `queue.enabled` | `INDEXNOW_QUEUE_ENABLED` | `true` | Submit from a queue worker instead of inline. |
| `queue.connection` | `INDEXNOW_QUEUE_CONNECTION` | app default | Queue connection for the job. |
| `queue.queue` | `INDEXNOW_QUEUE` | app default | Queue name for the job. |
| `queue.tries` | `INDEXNOW_QUEUE_TRIES` | `3` | Job attempts. |
| `queue.backoff` | — | `[60, 300, 900]` | Seconds between job attempts. |
| `schedule.enabled` | `INDEXNOW_SCHEDULE_ENABLED` | `false` | Register `indexnow:flush` on the scheduler. |
| `schedule.cron` | `INDEXNOW_SCHEDULE_CRON` | `*/5 * * * *` | How often to flush. |
| `key_route.enabled` | `INDEXNOW_KEY_ROUTE_ENABLED` | `false` | Serve the key at `/{key}.txt`. |
| `key_route.middleware` | — | `[]` | Middleware for that route. |

### Several engines

Participating engines forward submissions to each other, so the single `api.indexnow.org` endpoint
is normally enough. Configure more only when you want to notify an engine independently — for
example because it was verified with a different key:

```php
'engines' => [
    'indexnow' => ['endpoint' => 'https://api.indexnow.org/indexnow'],
    'yandex' => [
        'endpoint' => 'https://yandex.com/indexnow',
        'key' => env('INDEXNOW_YANDEX_KEY'),
    ],
],
```

## Pending URL stores

| Driver | Survives a cache flush | Needs a migration | Good for |
| --- | --- | --- | --- |
| `cache` (default) | no | no | most applications |
| `database` | yes | yes | high-value pages you cannot afford to lose |
| `array` | no | no | tests |

The cache store keeps everything in one entry and takes a cache lock around every mutation when the
underlying store supports one (Redis, Memcached, DynamoDB, database), so concurrent requests do not
overwrite each other.

For the database store:

```bash
php artisan vendor:publish --tag=indexnow-migrations
php artisan migrate
```

```env
INDEXNOW_STORE=database
```

URLs are deduplicated on a SHA-256 hash rather than on the URL column, so long URLs cannot outgrow
the unique index.

Anything implementing `SlimAD\IndexNow\Store\UrlStore` works — bind your own in a service provider:

```php
$this->app->singleton(\SlimAD\IndexNow\Store\UrlStore::class, fn () => new RedisStreamUrlStore(...));
```

## Events

| Event | When |
| --- | --- |
| `SlimAD\IndexNow\Laravel\Events\UrlsQueued` | URLs were added to the pending store (`$event->urls`) |
| `SlimAD\IndexNow\Laravel\Events\SubmissionFailed` | one engine rejected the batch or was unreachable (`$event->failure`) |
| `SlimAD\IndexNow\Laravel\Events\SubmissionCompleted` | a flush finished, successful or not (`$event->result`) |

```php
Event::listen(SubmissionFailed::class, function (SubmissionFailed $event): void {
    Log::warning('IndexNow rejected a submission', [
        'endpoint' => $event->failure->endpoint,
        'status' => $event->failure->statusCode,
    ]);
});
```

## Testing your own application

The package submits through Laravel's HTTP client, so `Http::fake()` works as usual:

```php
Http::fake(['https://api.indexnow.org/*' => Http::response('', 202)]);

IndexNow::submit('https://example.com/a');
IndexNow::flush();

Http::assertSent(fn ($request) => $request->data()['urlList'] === ['https://example.com/a']);
```

Use the `array` store and turn queueing off to keep tests synchronous:

```php
config()->set('indexnow.store', 'array');
config()->set('indexnow.queue.enabled', false);
```

Or turn the package off entirely for a test:

```php
config()->set('indexnow.enabled', false);
```

## Troubleshooting

**`IndexNow is not configured: "indexnow.host" is empty.`** — set `INDEXNOW_HOST`. Run
`php artisan indexnow:status` to see what the package resolved.

**URLs are reported as discarded.** — IndexNow only accepts URLs on the submitted host, and matching
is exact: `example.com` does not cover `www.example.com`. Set `INDEXNOW_HOST` to the host your
canonical URLs actually use.

**`rejected the submission with status 403`** — the endpoint could not read your key at
`key_location`. Open the URL in a browser: it has to return the key as `text/plain`.

**Nothing is submitted.** — check `indexnow.enabled`, that the scheduler is running, and that a
queue worker is processing the configured connection. `php artisan indexnow:flush --sync` submits
immediately and prints what happened.

## Quality gates

* PHP 8.2, 8.3 and 8.4 × Laravel 11, 12 and 13, plus a lowest-dependency run
* 100% line coverage, enforced in CI
* Mutation testing with [Infection](https://infection.github.io/)
* Larastan level 8, no baseline
* Laravel Pint for the coding standard
* `composer audit` and Dependabot

```bash
composer ci          # cs + phpstan + test + infection
composer test
composer cs:fix
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT — see [LICENSE](LICENSE).
