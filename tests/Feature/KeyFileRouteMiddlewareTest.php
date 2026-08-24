<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Routing\Router;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class KeyFileRouteMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('indexnow.key_route.enabled', true);
        $config->set('indexnow.key_route.middleware', ['first' => 'throttle:60,1', 'second' => 'cache.headers:public']);
    }

    public function test_the_configured_middleware_is_applied(): void
    {
        $route = $this->container()->make(Router::class)->getRoutes()->getByName('indexnow.key');

        self::assertNotNull($route);
        self::assertSame(['throttle:60,1', 'cache.headers:public'], $route->middleware());
    }
}
