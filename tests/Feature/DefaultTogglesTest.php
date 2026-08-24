<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

/**
 * Both the key route and the scheduler are opt-in: an application that installs
 * the package must not suddenly answer on a new URL or start submitting on a
 * timer.
 */
final class DefaultTogglesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('indexnow.key_route.enabled', null);
        $config->set('indexnow.schedule.enabled', null);
    }

    public function test_the_key_route_is_off_unless_it_is_turned_on(): void
    {
        self::assertNull($this->container()->make(Router::class)->getRoutes()->getByName('indexnow.key'));
    }

    public function test_the_scheduler_is_off_unless_it_is_turned_on(): void
    {
        $events = array_filter(
            $this->container()->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains($event->command ?? '', 'indexnow:flush'),
        );

        self::assertSame([], $events);
    }
}
