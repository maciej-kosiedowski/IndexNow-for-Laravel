<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Jobs\SubmitQueuedUrlsJob;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class SubmitQueuedUrlsJobTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function test_it_uses_the_documented_defaults(): void
    {
        $job = new SubmitQueuedUrlsJob;

        self::assertSame(3, $job->tries);
        self::assertSame([60, 300, 900], $job->backoff);
        self::assertSame(300, $job->uniqueFor);
        self::assertSame('indexnow-flush', $job->uniqueId());
    }

    public function test_it_accepts_custom_retry_settings(): void
    {
        $job = new SubmitQueuedUrlsJob(5, [1, 2], 30);

        self::assertSame(5, $job->tries);
        self::assertSame([1, 2], $job->backoff);
        self::assertSame(30, $job->uniqueFor);
    }

    public function test_handle_drains_the_queue(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->container()->make(IndexNowManager::class);
        $manager->submit('https://example.com/a');

        (new SubmitQueuedUrlsJob)->handle($manager);

        self::assertSame(0, $manager->pending());
        Http::assertSentCount(1);
    }

    public function test_it_runs_through_the_bus_on_the_sync_connection(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->container()->make(IndexNowManager::class);
        $manager->submit('https://example.com/a');

        $this->container()->make(Dispatcher::class)->dispatch(new SubmitQueuedUrlsJob);

        self::assertSame(0, $manager->pending());
        Http::assertSentCount(1);
    }
}
