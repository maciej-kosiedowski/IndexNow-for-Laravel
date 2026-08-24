<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Routing\Router;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class KeyFileRouteDisabledTest extends TestCase
{
    public function test_the_route_is_not_registered_by_default(): void
    {
        self::assertNull($this->container()->make(Router::class)->getRoutes()->getByName('indexnow.key'));

        $this->get('/abcdef0123456789abcdef0123456789.txt')->assertNotFound();
    }
}
