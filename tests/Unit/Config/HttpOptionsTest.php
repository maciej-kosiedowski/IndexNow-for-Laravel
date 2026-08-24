<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Config;

use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;

final class HttpOptionsTest extends TestCase
{
    public function test_it_falls_back_to_the_documented_defaults(): void
    {
        $options = HttpOptions::fromConfig(new Repository([]));

        self::assertSame(10, $options->timeout);
        self::assertSame(5, $options->connectTimeout);
        self::assertSame(3, $options->retries);
        self::assertSame(250, $options->retryDelay);
        self::assertSame(
            'slimad-indexnow-laravel (+https://github.com/maciej-kosiedowski/IndexNow-for-Laravel)',
            $options->userAgent,
        );
    }

    public function test_it_reads_every_value(): void
    {
        $options = HttpOptions::fromConfig(new Repository(['indexnow' => ['http' => [
            'timeout' => 30,
            'connect_timeout' => 15,
            'retries' => 5,
            'retry_delay' => 1000,
            'user_agent' => 'acme-shop/2.1',
        ]]]));

        self::assertSame(30, $options->timeout);
        self::assertSame(15, $options->connectTimeout);
        self::assertSame(5, $options->retries);
        self::assertSame(1000, $options->retryDelay);
        self::assertSame('acme-shop/2.1', $options->userAgent);
    }

    public function test_it_always_makes_at_least_one_attempt(): void
    {
        foreach ([0, -1] as $configured) {
            $options = HttpOptions::fromConfig(new Repository(['indexnow' => ['http' => ['retries' => $configured]]]));

            self::assertSame(1, $options->retries);
        }
    }

    public function test_a_blank_user_agent_falls_back_to_the_package_default(): void
    {
        $options = HttpOptions::fromConfig(new Repository(['indexnow' => ['http' => ['user_agent' => '  ']]]));

        self::assertSame(HttpOptions::DEFAULT_USER_AGENT, $options->userAgent);
    }
}
