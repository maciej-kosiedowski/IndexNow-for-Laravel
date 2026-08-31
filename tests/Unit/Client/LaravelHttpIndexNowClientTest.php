<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SlimAD\IndexNow\Dto\SubmitRequest;
use SlimAD\IndexNow\Exception\SubmitFailedException;
use SlimAD\IndexNow\Laravel\Client\LaravelHttpIndexNowClient;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\ValueObject\Host;
use SlimAD\IndexNow\ValueObject\Key;
use SlimAD\IndexNow\ValueObject\KeyLocation;
use SlimAD\IndexNow\ValueObject\Url;

final class LaravelHttpIndexNowClientTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    private function client(int $retries = 1, string $userAgent = HttpOptions::DEFAULT_USER_AGENT): LaravelHttpIndexNowClient
    {
        return new LaravelHttpIndexNowClient(
            $this->container()->make(Factory::class),
            new HttpOptions(10, 5, $retries, 0, $userAgent),
        );
    }

    private function request(): SubmitRequest
    {
        return new SubmitRequest(
            self::ENDPOINT,
            new Host('example.com'),
            new Key('abcdef0123456789'),
            KeyLocation::fromString('https://example.com/abcdef0123456789.txt'),
            new Url('https://example.com/a'),
            new Url('https://example.com/b'),
        );
    }

    public function test_it_posts_the_payload(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 202)]);

        $this->client()->submit($this->request());

        Http::assertSent(static function (Request $request): bool {
            self::assertSame('POST', $request->method());
            self::assertSame(self::ENDPOINT, $request->url());
            self::assertSame([
                'host' => 'example.com',
                'key' => 'abcdef0123456789',
                'keyLocation' => 'https://example.com/abcdef0123456789.txt',
                'urlList' => ['https://example.com/a', 'https://example.com/b'],
            ], $request->data());
            self::assertSame('application/json', $request->header('Content-Type')[0]);
            self::assertSame('application/json', $request->header('Accept')[0]);
            self::assertSame(
                HttpOptions::DEFAULT_USER_AGENT,
                $request->header('User-Agent')[0],
            );

            return true;
        });
    }

    public function test_it_sends_the_configured_user_agent(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 200)]);

        $this->client(userAgent: 'acme-shop/2.1')->submit($this->request());

        Http::assertSent(static fn (Request $request): bool => $request->header('User-Agent')[0] === 'acme-shop/2.1');
    }

    public function test_it_accepts_any_successful_status(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 200)]);

        $this->client()->submit($this->request());

        Http::assertSentCount(1);
    }

    public function test_it_reports_a_rejection(): void
    {
        Http::fake([self::ENDPOINT => Http::response('key not found', 403)]);

        try {
            $this->client()->submit($this->request());

            self::fail('A rejected submission must throw.');
        } catch (SubmitFailedException $exception) {
            self::assertSame(self::ENDPOINT, $exception->endpoint);
            self::assertSame(403, $exception->statusCode);
            self::assertSame('key not found', $exception->responseBody);
            self::assertStringContainsString('403', $exception->getMessage());
        }
    }

    public function test_it_reports_a_connection_failure(): void
    {
        Http::fake(static fn (): never => throw new ConnectionException('cURL error 28: timed out'));

        try {
            $this->client()->submit($this->request());

            self::fail('A connection failure must throw.');
        } catch (SubmitFailedException $exception) {
            self::assertSame(self::ENDPOINT, $exception->endpoint);
            self::assertNull($exception->statusCode);
            self::assertNull($exception->responseBody);
            self::assertStringContainsString('timed out', $exception->getMessage());
        }
    }

    public function test_it_retries_before_giving_up(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push('temporary', 500)
            ->push('', 202)]);

        $this->client(retries: 3)->submit($this->request());

        Http::assertSentCount(2);
    }

    public function test_it_gives_up_after_the_configured_number_of_attempts(): void
    {
        Http::fake([self::ENDPOINT => Http::response('still broken', 503)]);

        $client = $this->client(retries: 2);

        try {
            $client->submit($this->request());

            self::fail('A permanent failure must throw.');
        } catch (SubmitFailedException $exception) {
            self::assertSame(503, $exception->statusCode);
        }

        Http::assertSentCount(2);
    }
}
