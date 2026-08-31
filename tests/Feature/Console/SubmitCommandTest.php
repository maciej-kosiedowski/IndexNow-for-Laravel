<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature\Console;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class SubmitCommandTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function test_it_queues_the_given_urls(): void
    {
        Http::fake();

        $this->command('indexnow:submit', ['url' => ['https://example.com/a', 'https://example.com/b']])
            ->expectsOutputToContain('Queued 2 URL(s).')
            ->assertSuccessful();

        self::assertSame(2, $this->container()->make(IndexNowManager::class)->pending());
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_value_that_is_not_a_url(): void
    {
        $this->command('indexnow:submit', ['url' => ['not-a-url']])
            ->expectsOutputToContain('is not a valid http/https URL')
            ->assertExitCode(2);

        self::assertSame(0, $this->container()->make(IndexNowManager::class)->pending());
    }

    public function test_it_reports_that_the_package_is_disabled(): void
    {
        $this->config()->set('indexnow.enabled', false);
        $this->container()->forgetInstance(IndexNowManager::class);

        $this->command('indexnow:submit', ['url' => ['https://example.com/a']])
            ->expectsOutputToContain('IndexNow is disabled')
            ->assertSuccessful();
    }

    public function test_flush_submits_immediately_even_when_queueing_is_enabled(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->container()->forgetInstance(IndexNowManager::class);

        Bus::fake();
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->command('indexnow:submit', ['url' => ['https://example.com/a'], '--flush' => true])
            ->expectsOutputToContain('Submitted 1 URL(s).')
            ->assertSuccessful();

        Bus::assertNothingDispatched();
        Http::assertSentCount(1);
    }

    public function test_flush_submits_immediately(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->command('indexnow:submit', ['url' => ['https://example.com/a'], '--flush' => true])
            ->expectsOutputToContain('Queued 1 URL(s).')
            ->expectsOutputToContain('Submitted 1 URL(s).')
            ->assertSuccessful();

        Http::assertSentCount(1);
        self::assertSame(0, $this->container()->make(IndexNowManager::class)->pending());
    }
}
