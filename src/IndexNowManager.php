<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel;

use Closure;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use SlimAD\IndexNow\Job\SubmitJob;
use SlimAD\IndexNow\Job\SubmitJobResult;
use SlimAD\IndexNow\Laravel\Config\QueueOptions;
use SlimAD\IndexNow\Laravel\Events\SubmissionCompleted;
use SlimAD\IndexNow\Laravel\Events\SubmissionFailed;
use SlimAD\IndexNow\Laravel\Events\UrlsQueued;
use SlimAD\IndexNow\Laravel\Jobs\SubmitQueuedUrlsJob;
use SlimAD\IndexNow\Store\UrlStore;
use SlimAD\IndexNow\ValueObject\Url;

/**
 * The application-facing entry point, also reachable through the `IndexNow`
 * facade.
 *
 * Queueing a URL is cheap (a single store write); the actual HTTP submission
 * happens when {@see self::flush()} runs, normally from the scheduler or a queue
 * worker.
 */
final readonly class IndexNowManager
{
    /**
     * The submit job is resolved lazily: building it needs a complete IndexNow
     * configuration, and queueing a URL has to keep working on an application
     * that has installed the package but not configured it yet.
     *
     * @param  Closure(): SubmitJob  $job
     */
    public function __construct(
        private UrlStore $store,
        private Closure $job,
        private EventDispatcher $events,
        private BusDispatcher $bus,
        private bool $enabled,
        private QueueOptions $queue,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function usesQueue(): bool
    {
        return $this->enabled && $this->queue->enabled;
    }

    /**
     * Queues one or more URLs for the next submission.
     *
     * @return int the number of distinct URLs that were queued
     */
    public function submit(Url|string ...$urls): int
    {
        return $this->submitMany($urls);
    }

    /**
     * @param  iterable<Url|string>  $urls
     * @return int the number of distinct URLs that were queued
     */
    public function submitMany(iterable $urls): int
    {
        if (! $this->enabled) {
            return 0;
        }

        $queued = [];

        foreach ($urls as $url) {
            $url = $url instanceof Url ? $url : new Url($url);

            $this->store->add($url);

            $queued[$url->value] = $url->value;
        }

        if ($queued === []) {
            return 0;
        }

        $queued = array_values($queued);

        $this->events->dispatch(new UrlsQueued($queued));

        return \count($queued);
    }

    public function pending(): int
    {
        return $this->store->count();
    }

    public function clear(): void
    {
        $this->store->clear();
    }

    public function store(): UrlStore
    {
        return $this->store;
    }

    /**
     * Submits everything that is queued, right now, in this process.
     */
    public function flush(): SubmitJobResult
    {
        if (! $this->enabled) {
            return SubmitJobResult::idle();
        }

        $result = ($this->job)()->run();

        foreach ($result->failures as $failure) {
            $this->events->dispatch(new SubmissionFailed($failure));
        }

        $this->events->dispatch(new SubmissionCompleted($result));

        return $result;
    }

    /**
     * Hands the submission over to a queue worker.
     *
     * Nothing is submitted when this returns false - the package is disabled or
     * queueing is off, and it is up to the caller to run {@see self::flush()}
     * instead.
     */
    public function dispatchFlush(): bool
    {
        if (! $this->usesQueue()) {
            return false;
        }

        $job = new SubmitQueuedUrlsJob($this->queue->tries, $this->queue->backoff);
        $job->onConnection($this->queue->connection);
        $job->onQueue($this->queue->queue);

        $this->bus->dispatch($job);

        return true;
    }
}
