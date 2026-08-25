<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Client\IndexNowClient;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;
use SlimAD\IndexNow\Laravel\Config\QueueOptions;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Jobs\SubmitQueuedUrlsJob;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

/**
 * The package reads its settings from a plain config array, so every option has
 * to survive being written as a string (the shape `env()` returns) and being
 * left out entirely.
 */
final class ConfigurationOptionsTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    private function freshManager(): IndexNowManager
    {
        $this->container()->forgetInstance(HttpOptions::class);
        $this->container()->forgetInstance(QueueOptions::class);
        $this->container()->forgetInstance(IndexNowClient::class);
        $this->container()->forgetInstance(IndexNowManager::class);

        return $this->container()->make(IndexNowManager::class);
    }

    public function test_numeric_strings_are_accepted_for_http_options(): void
    {
        $this->config()->set('indexnow.http.retries', '3');
        $this->config()->set('indexnow.http.retry_delay', '0');
        $this->config()->set('indexnow.http.timeout', '20');

        Http::fake([self::ENDPOINT => Http::response('down', 503)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a');
        $manager->flush();

        Http::assertSentCount(3);
    }

    public function test_http_options_fall_back_to_their_defaults(): void
    {
        $this->config()->set('indexnow.http.retries', null);
        $this->config()->set('indexnow.http.retry_delay', 0);

        Http::fake([self::ENDPOINT => Http::response('down', 503)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a');
        $manager->flush();

        Http::assertSentCount(3);
    }

    public function test_the_user_agent_can_be_overridden(): void
    {
        $this->config()->set('indexnow.http.user_agent', 'acme-shop/2.1');
        $this->config()->set('indexnow.http.retries', 1);

        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a');
        $manager->flush();

        Http::assertSent(static fn ($request): bool => $request->header('User-Agent')[0] === 'acme-shop/2.1');
    }

    /**
     * @return iterable<string, array{mixed, list<int>}>
     */
    public static function backoffProvider(): iterable
    {
        yield 'explicit list' => [[5, 10], [5, 10]];
        yield 'non-integer entries are dropped' => [[5, 'later', -1, 10], [5, 10]];
        yield 'not a list at all' => ['soon', [60, 300, 900]];
        yield 'nothing usable left' => [['soon'], [60, 300, 900]];
        yield 'missing' => [null, [60, 300, 900]];
    }

    /**
     * @param  list<int>  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('backoffProvider')]
    public function test_the_queue_backoff_is_normalised(mixed $configured, array $expected): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->config()->set('indexnow.queue.backoff', $configured);

        Bus::fake();

        $this->freshManager()->dispatchFlush();

        Bus::assertDispatched(SubmitQueuedUrlsJob::class, static function (SubmitQueuedUrlsJob $job) use ($expected): bool {
            self::assertSame($expected, $job->backoff);

            return true;
        });
    }

    public function test_the_batch_size_splits_the_queue(): void
    {
        $this->config()->set('indexnow.batch_size', 1);
        $this->config()->set('indexnow.http.retries', 1);
        $this->container()->forgetInstance(\SlimAD\IndexNow\Job\SubmitJob::class);

        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a', 'https://example.com/b');
        $manager->flush();

        Http::assertSentCount(2);
    }

    public function test_a_string_batch_size_splits_the_queue(): void
    {
        $this->config()->set('indexnow.batch_size', '2');
        $this->config()->set('indexnow.http.retries', 1);
        $this->container()->forgetInstance(\SlimAD\IndexNow\Job\SubmitJob::class);

        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a', 'https://example.com/b', 'https://example.com/c');
        $manager->flush();

        Http::assertSentCount(2);
    }

    public function test_the_master_switch_accepts_the_integer_env_shape(): void
    {
        $this->config()->set('indexnow.enabled', 0);

        $manager = $this->freshManager();

        self::assertFalse($manager->isEnabled());
        self::assertSame(0, $manager->submit('https://example.com/a'));
    }

    public function test_an_oversized_batch_size_is_capped_at_the_protocol_limit(): void
    {
        $this->config()->set('indexnow.batch_size', 999999);
        $this->config()->set('indexnow.http.retries', 1);
        $this->container()->forgetInstance(\SlimAD\IndexNow\Job\SubmitJob::class);

        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a', 'https://example.com/b');
        $manager->flush();

        Http::assertSentCount(1);
    }

    public function test_a_zero_batch_size_still_submits(): void
    {
        $this->config()->set('indexnow.batch_size', 0);
        $this->config()->set('indexnow.http.retries', 1);
        $this->container()->forgetInstance(\SlimAD\IndexNow\Job\SubmitJob::class);

        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $manager = $this->freshManager();
        $manager->submit('https://example.com/a', 'https://example.com/b');
        $manager->flush();

        Http::assertSentCount(2);
    }

    public function test_the_queue_tries_default_to_three(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->config()->set('indexnow.queue.tries', null);

        Bus::fake();

        $this->freshManager()->dispatchFlush();

        Bus::assertDispatched(SubmitQueuedUrlsJob::class, static function (SubmitQueuedUrlsJob $job): bool {
            self::assertSame(3, $job->tries);

            return true;
        });
    }

    public function test_the_queue_connection_and_name_default_to_the_application_defaults(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->config()->set('indexnow.queue.connection', '   ');
        $this->config()->set('indexnow.queue.queue', null);

        Bus::fake();

        $this->freshManager()->dispatchFlush();

        Bus::assertDispatched(SubmitQueuedUrlsJob::class, static function (SubmitQueuedUrlsJob $job): bool {
            self::assertNull($job->connection);
            self::assertNull($job->queue);

            return true;
        });
    }
}
