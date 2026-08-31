# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

Initial implementation of the Laravel integration for `slimad/indexnow`:

* `IndexNowServiceProvider` with auto-discovery, a publishable `config/indexnow.php`, a publishable
  migration and container bindings for every seam.
* `IndexNowManager` and the `IndexNow` facade — `submit()`, `submitMany()`, `pending()`, `clear()`,
  `flush()` and `dispatchFlush()`.
* Pending URL stores: `CacheUrlStore` (locking when the cache store supports it), `DatabaseUrlStore`
  (SHA-256 deduplication so long URLs stay indexable) and the core `InMemoryUrlStore` for tests.
* `LaravelHttpIndexNowClient` — submissions go through Laravel's HTTP client, so timeouts, retries
  and `Http::fake()` work as expected.
* `SubmitQueuedUrlsJob` — a unique queued job with configurable tries and backoff.
* Artisan commands: `indexnow:flush`, `indexnow:submit`, `indexnow:status` and `indexnow:key`.
* Optional scheduler binding and an optional `/{key}.txt` route that serves the key file.
* `SubmitsToIndexNow` trait and `ProvidesIndexNowUrls` contract for Eloquent models.
* Events: `UrlsQueued`, `SubmissionCompleted` and `SubmissionFailed` - `readonly` classes, like
  every other service and value object the package binds, so nothing can be changed after it is
  constructed.
* Continuous integration: coding standards, static analysis, a Laravel 12/13 × PHP 8.2-8.4
  matrix, a 100% line coverage gate, mutation testing, `composer audit` and Dependabot.

[Unreleased]: https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/compare/master...HEAD
