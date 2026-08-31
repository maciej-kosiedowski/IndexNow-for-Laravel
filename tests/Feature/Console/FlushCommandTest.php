<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Jobs\SubmitQueuedUrlsJob;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class FlushCommandTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function test_it_reports_an_empty_queue(): void
    {
        Http::fake();

        $this->command('indexnow:flush')
            ->expectsOutputToContain('No pending URLs.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_reports_that_the_package_is_disabled(): void
    {
        $this->config()->set('indexnow.enabled', false);
        $this->container()->forgetInstance(IndexNowManager::class);

        $this->command('indexnow:flush')
            ->expectsOutputToContain('IndexNow is disabled')
            ->assertSuccessful();
    }

    public function test_it_submits_inline(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->container()->make(IndexNowManager::class)->submit('https://example.com/a');

        $this->command('indexnow:flush')
            ->doesntExpectOutputToContain('Discarded')
            ->expectsOutputToContain('Submitted 1 URL(s).')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_it_reports_discarded_urls(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->container()->make(IndexNowManager::class)
            ->submit('https://example.com/a', 'https://other.example/b');

        $this->command('indexnow:flush')
            ->expectsOutputToContain('Discarded 1 queued URL(s)')
            ->assertSuccessful();
    }

    public function test_it_fails_when_an_endpoint_rejects_the_batch(): void
    {
        Http::fake([self::ENDPOINT => Http::response('nope', 403)]);

        $this->container()->make(IndexNowManager::class)->submit('https://example.com/a');

        ['status' => $status, 'output' => $output] = $this->runCommand('indexnow:flush');

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('rejected the submission with status 403', $output);
        self::assertStringContainsString('1 URL(s) stay queued and will be retried on the next run.', $output);
    }

    public function test_it_dispatches_a_job_when_queueing_is_enabled(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->container()->forgetInstance(IndexNowManager::class);

        Bus::fake();
        Http::fake();

        $this->container()->make(IndexNowManager::class)->submit('https://example.com/a');

        $this->command('indexnow:flush')
            ->expectsOutputToContain('Dispatched a job to submit 1 pending URL(s).')
            ->assertSuccessful();

        Bus::assertDispatched(SubmitQueuedUrlsJob::class);
        Http::assertNothingSent();
    }

    public function test_sync_forces_an_inline_submission(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->container()->forgetInstance(IndexNowManager::class);

        Bus::fake();
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->container()->make(IndexNowManager::class)->submit('https://example.com/a');

        $this->command('indexnow:flush', ['--sync' => true])
            ->expectsOutputToContain('Submitted 1 URL(s).')
            ->assertSuccessful();

        Bus::assertNothingDispatched();
        Http::assertSentCount(1);
    }
}
