<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class KeyFileRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make('config')->set('indexnow.key_route.enabled', true);
    }

    public function test_it_serves_the_key_as_plain_text(): void
    {
        $response = $this->get('/abcdef0123456789abcdef0123456789.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        self::assertSame('abcdef0123456789abcdef0123456789', $response->getContent());
    }

    public function test_the_route_is_named(): void
    {
        self::assertSame(
            'http://localhost/abcdef0123456789abcdef0123456789.txt',
            route('indexnow.key'),
        );
    }

    public function test_another_key_is_not_served(): void
    {
        $this->get('/0000000000000000.txt')->assertNotFound();
    }

    public function test_it_returns404_when_the_key_disappears_from_the_configuration(): void
    {
        $this->config()->set('indexnow.key', null);

        $this->get('/abcdef0123456789abcdef0123456789.txt')->assertNotFound();
    }
}
