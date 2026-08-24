<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Exception\InvalidUrlException;
use SlimAD\IndexNow\Laravel\Events\SubmissionCompleted;
use SlimAD\IndexNow\Laravel\Events\SubmissionFailed;
use SlimAD\IndexNow\Laravel\Events\UrlsQueued;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Jobs\SubmitQueuedUrlsJob;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\Store\InMemoryUrlStore;
use SlimAD\IndexNow\ValueObject\Url;

final class IndexNowManagerTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    private function manager(): IndexNowManager
    {
        return $this->container()->make(IndexNowManager::class);
    }

    public function test_it_queues_urls(): void
    {
        $queued = $this->manager()->submit('https://example.com/a', new Url('https://example.com/b'));

        self::assertSame(2, $queued);
        self::assertSame(2, $this->manager()->pending());
    }

    public function test_it_counts_each_url_only_once(): void
    {
        $queued = $this->manager()->submit('https://example.com/a', 'https://example.com/a');

        self::assertSame(1, $queued);
        self::assertSame(1, $this->manager()->pending());
    }

    public function test_it_rejects_a_value_that_is_not_a_url(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->manager()->submit('not-a-url');
    }

    public function test_it_dispatches_an_event_for_queued_urls(): void
    {
        Event::fake([UrlsQueued::class]);

        $this->manager()->submit('https://example.com/a', 'https://example.com/a', 'https://example.com/b');

        Event::assertDispatched(UrlsQueued::class, static function (UrlsQueued $event): bool {
            self::assertSame(['https://example.com/a', 'https://example.com/b'], $event->urls);

            return true;
        });
    }

    public function test_it_does_not_dispatch_an_event_when_nothing_was_queued(): void
    {
        Event::fake([UrlsQueued::class]);

        self::assertSame(0, $this->manager()->submitMany([]));

        Event::assertNotDispatched(UrlsQueued::class);
    }

    public function test_it_ignores_everything_when_disabled(): void
    {
        $this->config()->set('indexnow.enabled', false);
        $this->container()->forgetInstance(IndexNowManager::class);

        Event::fake();
        Http::fake();

        $manager = $this->manager();

        self::assertFalse($manager->isEnabled());
        self::assertSame(0, $manager->submit('https://example.com/a'));
        self::assertSame(0, $manager->pending());
        self::assertFalse($manager->dispatchFlush());
        self::assertSame(0, $manager->flush()->submittedUrls);

        Http::assertNothingSent();
        Event::assertNothingDispatched();
    }

    public function test_it_exposes_the_underlying_store(): void
    {
        self::assertInstanceOf(InMemoryUrlStore::class, $this->manager()->store());
    }

    public function test_clear_empties_the_queue(): void
    {
        $manager = $this->manager();
        $manager->submit('https://example.com/a');

        $manager->clear();

        self::assertSame(0, $manager->pending());
    }

    public function test_flush_submits_and_dispatches_a_completed_event(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);
        Event::fake([SubmissionCompleted::class, SubmissionFailed::class]);

        $manager = $this->manager();
        $manager->submit('https://example.com/a', 'https://example.com/b');

        $result = $manager->flush();

        self::assertSame(2, $result->submittedUrls);
        self::assertTrue($result->isSuccess());
        self::assertSame(0, $manager->pending());

        Http::assertSentCount(1);
        Event::assertDispatched(SubmissionCompleted::class);
        Event::assertNotDispatched(SubmissionFailed::class);
    }

    public function test_flush_reports_failures_and_keeps_the_queue(): void
    {
        Http::fake([self::ENDPOINT => Http::response('nope', 403)]);
        Event::fake([SubmissionCompleted::class, SubmissionFailed::class]);

        $manager = $this->manager();
        $manager->submit('https://example.com/a');

        $result = $manager->flush();

        self::assertTrue($result->hasFailures());
        self::assertSame(1, $manager->pending());

        Event::assertDispatched(SubmissionFailed::class, static function (SubmissionFailed $event): bool {
            self::assertSame(403, $event->failure->statusCode);

            return true;
        });
        Event::assertDispatched(SubmissionCompleted::class);
    }

    public function test_flush_discards_urls_from_another_host(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->manager();
        $manager->submit('https://example.com/a', 'https://other.example/b');

        $result = $manager->flush();

        self::assertSame(1, $result->submittedUrls);
        self::assertSame(1, $result->discardedUrls);
        self::assertSame(0, $manager->pending());
    }

    public function test_dispatch_flush_does_nothing_when_queueing_is_off(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);
        Bus::fake();

        $manager = $this->manager();
        $manager->submit('https://example.com/a');

        self::assertFalse($manager->usesQueue());
        self::assertFalse($manager->dispatchFlush(), 'the caller has to flush inline instead');

        Bus::assertNothingDispatched();
        Http::assertNothingSent();
        self::assertSame(1, $manager->pending());
    }

    public function test_dispatch_flush_queues_the_job_when_queueing_is_on(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->config()->set('indexnow.queue.connection', 'redis');
        $this->config()->set('indexnow.queue.queue', 'seo');
        $this->config()->set('indexnow.queue.tries', 5);
        $this->container()->forgetInstance(IndexNowManager::class);

        Bus::fake();
        Http::fake();

        $manager = $this->manager();
        $manager->submit('https://example.com/a');

        self::assertTrue($manager->usesQueue());
        self::assertTrue($manager->dispatchFlush());

        Bus::assertDispatched(SubmitQueuedUrlsJob::class, static function (SubmitQueuedUrlsJob $job): bool {
            self::assertSame('redis', $job->connection);
            self::assertSame('seo', $job->queue);
            self::assertSame(5, $job->tries);
            self::assertSame([60, 300, 900], $job->backoff);
            self::assertSame('indexnow-flush', $job->uniqueId());

            return true;
        });
        Http::assertNothingSent();
    }
}
