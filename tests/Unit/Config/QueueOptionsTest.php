<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Config;

use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use SlimAD\IndexNow\Laravel\Config\QueueOptions;

final class QueueOptionsTest extends TestCase
{
    public function test_it_falls_back_to_the_documented_defaults(): void
    {
        $options = QueueOptions::fromConfig(new Repository([]));

        self::assertTrue($options->enabled, 'queueing is on unless it is turned off');
        self::assertNull($options->connection);
        self::assertNull($options->queue);
        self::assertSame(3, $options->tries);
        self::assertSame([60, 300, 900], $options->backoff);
    }

    public function test_it_reads_every_value(): void
    {
        $options = QueueOptions::fromConfig(new Repository(['indexnow' => ['queue' => [
            'enabled' => false,
            'connection' => 'redis',
            'queue' => 'seo',
            'tries' => 7,
            'backoff' => [1, 2, 3],
        ]]]));

        self::assertFalse($options->enabled);
        self::assertSame('redis', $options->connection);
        self::assertSame('seo', $options->queue);
        self::assertSame(7, $options->tries);
        self::assertSame([1, 2, 3], $options->backoff);
    }

    public function test_it_always_allows_at_least_one_attempt(): void
    {
        $options = QueueOptions::fromConfig(new Repository(['indexnow' => ['queue' => ['tries' => 0]]]));

        self::assertSame(1, $options->tries);
    }

    public function test_blank_connection_and_queue_mean_the_application_defaults(): void
    {
        $options = QueueOptions::fromConfig(new Repository(['indexnow' => ['queue' => [
            'connection' => '   ',
            'queue' => '',
        ]]]));

        self::assertNull($options->connection);
        self::assertNull($options->queue);
    }
}
