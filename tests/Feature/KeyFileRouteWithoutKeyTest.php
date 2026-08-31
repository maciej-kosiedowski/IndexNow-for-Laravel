<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Routing\Router;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class KeyFileRouteWithoutKeyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('indexnow.key_route.enabled', true);
        $config->set('indexnow.key', null);
    }

    public function test_no_route_is_registered_without_a_key(): void
    {
        self::assertNull($this->container()->make(Router::class)->getRoutes()->getByName('indexnow.key'));
    }
}
