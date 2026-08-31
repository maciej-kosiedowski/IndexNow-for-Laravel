<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Store;

use Illuminate\Config\Repository;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Laravel\Store\CacheUrlStore;
use SlimAD\IndexNow\Laravel\Store\DatabaseUrlStore;
use SlimAD\IndexNow\Laravel\Store\UrlStoreFactory;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\Store\InMemoryUrlStore;

final class UrlStoreFactoryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $indexnow
     */
    private function factory(array $indexnow): UrlStoreFactory
    {
        return new UrlStoreFactory($this->container(), new Repository(['indexnow' => $indexnow]));
    }

    public function test_it_builds_the_array_store(): void
    {
        $store = $this->factory([
            'store' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ])->make();

        self::assertInstanceOf(InMemoryUrlStore::class, $store);
    }

    public function test_it_builds_the_cache_store(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache', 'store' => 'array', 'key' => 'k', 'ttl' => 60, 'lock_seconds' => 9]],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame('k', $store->key);
        self::assertSame(60, $store->ttl);
        self::assertSame(9, $store->lockSeconds);
    }

    public function test_it_builds_the_database_store(): void
    {
        $store = $this->factory([
            'store' => 'database',
            'stores' => ['database' => ['driver' => 'database', 'connection' => null, 'table' => 'seo_urls']],
        ])->make();

        self::assertInstanceOf(DatabaseUrlStore::class, $store);
        self::assertSame('seo_urls', $store->table);
    }

    public function test_the_database_table_falls_back_to_the_documented_default(): void
    {
        $store = $this->factory([
            'store' => 'database',
            'stores' => ['database' => ['driver' => 'database']],
        ])->make();

        self::assertInstanceOf(DatabaseUrlStore::class, $store);
        self::assertSame('indexnow_urls', $store->table);
    }

    public function test_the_driver_is_independent_of_the_store_name(): void
    {
        $store = $this->factory([
            'store' => 'primary',
            'stores' => ['primary' => ['driver' => 'array']],
        ])->make();

        self::assertInstanceOf(InMemoryUrlStore::class, $store);
    }

    public function test_the_store_name_is_used_as_the_driver_when_none_is_given(): void
    {
        $store = $this->factory([
            'store' => 'array',
            'stores' => ['array' => []],
        ])->make();

        self::assertInstanceOf(InMemoryUrlStore::class, $store);
    }

    public function test_it_falls_back_to_the_cache_store(): void
    {
        $store = $this->factory([
            'store' => null,
            'stores' => ['cache' => ['driver' => 'cache']],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
    }

    public function test_it_rejects_an_undefined_store(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('IndexNow store "redis" is not defined in "indexnow.stores". Available: array, cache.');

        $this->factory([
            'store' => 'redis',
            'stores' => ['array' => [], 'cache' => []],
        ])->make();
    }

    public function test_it_lists_numeric_store_names(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('Available: 0, 1.');

        $this->factory([
            'store' => 'redis',
            'stores' => [[], []],
        ])->make();
    }

    public function test_it_reports_when_no_store_is_defined_at_all(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('Available: <none>.');

        $this->factory(['store' => 'cache', 'stores' => []])->make();
    }

    public function test_it_rejects_an_unsupported_driver(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('IndexNow store "elastic" uses the unsupported driver "elastic"');

        $this->factory([
            'store' => 'elastic',
            'stores' => ['elastic' => ['driver' => 'elastic']],
        ])->make();
    }

    public function test_cache_settings_fall_back_to_the_documented_defaults(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache', 'store' => '', 'key' => '  ', 'ttl' => 'nope', 'lock_seconds' => -3]],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame('indexnow:pending', $store->key);
        self::assertSame(604800, $store->ttl);
        self::assertSame(5, $store->lockSeconds);
    }

    public function test_cache_settings_fall_back_when_they_are_missing_entirely(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache']],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame('indexnow:pending', $store->key);
        self::assertSame(604800, $store->ttl);
        self::assertSame(5, $store->lockSeconds);
    }

    public function test_numeric_string_settings_are_accepted(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache', 'ttl' => '120', 'lock_seconds' => '3']],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame(120, $store->ttl);
        self::assertSame(3, $store->lockSeconds);
    }

    public function test_a_zero_setting_falls_back_to_the_default(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache', 'ttl' => 0, 'lock_seconds' => 0]],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame(604800, $store->ttl);
        self::assertSame(5, $store->lockSeconds);
    }

    public function test_the_configured_cache_store_is_used(): void
    {
        $store = $this->factory([
            'store' => 'cache',
            'stores' => ['cache' => ['driver' => 'cache', 'store' => 'array', 'key' => 'custom:key']],
        ])->make();

        self::assertInstanceOf(CacheUrlStore::class, $store);
        self::assertSame('custom:key', $store->key);
    }
}
