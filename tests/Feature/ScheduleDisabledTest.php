<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class ScheduleDisabledTest extends TestCase
{
    public function test_nothing_is_scheduled_by_default(): void
    {
        $events = array_filter(
            $this->container()->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains($event->command ?? '', 'indexnow:flush'),
        );

        self::assertSame([], $events);
    }
}
