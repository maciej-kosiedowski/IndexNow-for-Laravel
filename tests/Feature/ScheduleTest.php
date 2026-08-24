<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class ScheduleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('indexnow.schedule.enabled', true);
        $config->set('indexnow.schedule.cron', '*/7 * * * *');
    }

    public function test_it_schedules_the_flush_command(): void
    {
        $events = array_values(array_filter(
            $this->container()->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains($event->command ?? '', 'indexnow:flush'),
        ));

        self::assertCount(1, $events);
        self::assertSame('*/7 * * * *', $events[0]->expression);
        self::assertNotEmpty($events[0]->withoutOverlapping);
        self::assertTrue($events[0]->runInBackground);
    }
}
