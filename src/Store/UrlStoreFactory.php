<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Store;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Store\InMemoryUrlStore;
use SlimAD\IndexNow\Store\UrlStore;

/**
 * Builds the pending URL store named by `indexnow.store`.
 */
final readonly class UrlStoreFactory
{
    public const DEFAULT_STORE = 'cache';

    public const DEFAULT_CACHE_KEY = 'indexnow:pending';

    public const DEFAULT_CACHE_TTL = 604800;

    public const DEFAULT_LOCK_SECONDS = 5;

    public const DEFAULT_TABLE = 'indexnow_urls';

    public function __construct(
        private Container $container,
        private Repository $config,
    ) {}

    public function make(): UrlStore
    {
        $name = Str::squish((string) $this->config->get('indexnow.store')) ?: self::DEFAULT_STORE;

        $stores = $this->config->get('indexnow.stores');
        $stores = \is_array($stores) ? $stores : [];

        // Not Arr::get(): a store name may legitimately contain a dot, and dot
        // traversal would then look up a nested array that does not exist.
        $settings = $stores[$name] ?? null;

        if (! \is_array($settings)) {
            throw IndexNowConfigurationException::unknownStore($name, array_keys($stores));
        }

        $driver = Str::squish((string) Arr::get($settings, 'driver')) ?: $name;

        return match ($driver) {
            'array' => new InMemoryUrlStore,
            'cache' => $this->makeCacheStore($settings),
            'database' => $this->makeDatabaseStore($settings),
            default => throw IndexNowConfigurationException::unsupportedStoreDriver($name, $driver),
        };
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function makeCacheStore(array $settings): CacheUrlStore
    {
        $cache = $this->container->make(CacheFactory::class);

        return new CacheUrlStore(
            $cache->store(self::text($settings, 'store')),
            self::text($settings, 'key') ?? self::DEFAULT_CACHE_KEY,
            self::positiveInt($settings, 'ttl', self::DEFAULT_CACHE_TTL),
            self::positiveInt($settings, 'lock_seconds', self::DEFAULT_LOCK_SECONDS),
        );
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function makeDatabaseStore(array $settings): DatabaseUrlStore
    {
        $connections = $this->container->make(ConnectionResolverInterface::class);

        return new DatabaseUrlStore(
            $connections->connection(self::text($settings, 'connection')),
            self::text($settings, 'table') ?? self::DEFAULT_TABLE,
        );
    }

    /**
     * A trimmed, non-blank setting, or null when it is not set.
     *
     * @param  array<array-key, mixed>  $settings
     */
    private static function text(array $settings, string $key): ?string
    {
        return Str::squish((string) Arr::get($settings, $key)) ?: null;
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private static function positiveInt(array $settings, string $key, int $default): int
    {
        $value = (int) Arr::get($settings, $key);

        return $value > 0 ? $value : $default;
    }
}
