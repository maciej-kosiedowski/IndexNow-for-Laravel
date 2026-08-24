<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class ScheduleDefaultCronTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('indexnow.schedule.enabled', true);
        $config->set('indexnow.schedule.cron', null);
    }

    public function test_it_falls_back_to_every_five_minutes(): void
    {
        $events = array_values(array_filter(
            $this->container()->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains($event->command ?? '', 'indexnow:flush'),
        ));

        self::assertCount(1, $events);
        self::assertSame('*/5 * * * *', $events[0]->expression);
    }
}
