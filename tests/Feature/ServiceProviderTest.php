<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use SlimAD\IndexNow\Client\IndexNowClient;
use SlimAD\IndexNow\Config\IndexNowConfig;
use SlimAD\IndexNow\Job\SubmitJob;
use SlimAD\IndexNow\Laravel\Client\LaravelHttpIndexNowClient;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;
use SlimAD\IndexNow\Laravel\Config\IndexNowConfigFactory;
use SlimAD\IndexNow\Laravel\Config\QueueOptions;
use SlimAD\IndexNow\Laravel\Facades\IndexNow;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Store\CacheUrlStore;
use SlimAD\IndexNow\Laravel\Store\UrlStoreFactory;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\Service\IndexNowService;
use SlimAD\IndexNow\Store\InMemoryUrlStore;
use SlimAD\IndexNow\Store\UrlStore;

final class ServiceProviderTest extends TestCase
{
    public function test_it_merges_the_package_configuration(): void
    {
        self::assertSame(10000, $this->config()->get('indexnow.batch_size'));
        self::assertIsArray($this->config()->get('indexnow.engines'));
    }

    public function test_it_binds_every_service(): void
    {
        self::assertInstanceOf(InMemoryUrlStore::class, $this->container()->make(UrlStore::class));
        self::assertInstanceOf(LaravelHttpIndexNowClient::class, $this->container()->make(IndexNowClient::class));
        self::assertInstanceOf(IndexNowConfig::class, $this->container()->make(IndexNowConfig::class));
        self::assertInstanceOf(IndexNowService::class, $this->container()->make(IndexNowService::class));
        self::assertInstanceOf(SubmitJob::class, $this->container()->make(SubmitJob::class));
        self::assertInstanceOf(IndexNowManager::class, $this->container()->make(IndexNowManager::class));
        self::assertInstanceOf(IndexNowManager::class, $this->container()->make('indexnow'));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function singletonProvider(): iterable
    {
        yield 'config factory' => [IndexNowConfigFactory::class];
        yield 'config' => [IndexNowConfig::class];
        yield 'store factory' => [UrlStoreFactory::class];
        yield 'store' => [UrlStore::class];
        yield 'http options' => [HttpOptions::class];
        yield 'queue options' => [QueueOptions::class];
        yield 'client' => [IndexNowClient::class];
        yield 'service' => [IndexNowService::class];
        yield 'job' => [SubmitJob::class];
        yield 'manager' => [IndexNowManager::class];
    }

    /**
     * Resolving twice has to hand back the same object: a second pending-URL
     * store would quietly split the queue in two.
     *
     * @param  class-string  $abstract
     */
    #[DataProvider('singletonProvider')]
    public function test_services_are_singletons(string $abstract): void
    {
        self::assertSame($this->container()->make($abstract), $this->container()->make($abstract));
    }

    public function test_the_alias_resolves_the_same_manager(): void
    {
        self::assertSame($this->container()->make(IndexNowManager::class), $this->container()->make('indexnow'));
    }

    public function test_the_package_is_enabled_unless_it_is_turned_off(): void
    {
        $this->config()->set('indexnow.enabled', null);
        $this->container()->forgetInstance(IndexNowManager::class);

        self::assertTrue($this->container()->make(IndexNowManager::class)->isEnabled());
    }

    public function test_the_facade_resolves_the_manager(): void
    {
        self::assertSame(0, IndexNow::pending());
        self::assertTrue(IndexNow::isEnabled());
    }

    public function test_it_switches_to_the_cache_store(): void
    {
        $this->config()->set('indexnow.store', 'cache');
        $this->refreshApplication();
        $this->config()->set('indexnow.store', 'cache');

        self::assertInstanceOf(CacheUrlStore::class, $this->container()->make(UrlStore::class));
    }

    public function test_it_caps_the_batch_size_at_the_protocol_limit(): void
    {
        $this->config()->set('indexnow.batch_size', 999999);

        self::assertInstanceOf(SubmitJob::class, $this->container()->make(SubmitJob::class));
    }

    public function test_it_registers_the_artisan_commands(): void
    {
        $commands = array_keys($this->container()->make(Kernel::class)->all());

        self::assertContains('indexnow:flush', $commands);
        self::assertContains('indexnow:submit', $commands);
        self::assertContains('indexnow:status', $commands);
        self::assertContains('indexnow:key', $commands);
    }
}
