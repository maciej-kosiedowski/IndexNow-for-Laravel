<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Store;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use SlimAD\IndexNow\Laravel\Config\ConfigValues;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Store\InMemoryUrlStore;
use SlimAD\IndexNow\Store\UrlStore;

/**
 * Builds the pending URL store named by `indexnow.store`.
 */
final class UrlStoreFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    public const DEFAULT_STORE = 'cache';

    public const DEFAULT_CACHE_KEY = 'indexnow:pending';

    public const DEFAULT_CACHE_TTL = 604800;

    public const DEFAULT_LOCK_SECONDS = 5;

    public const DEFAULT_TABLE = 'indexnow_urls';

    public function make(): UrlStore
    {
        $name = ConfigValues::string($this->config, 'indexnow.store') ?? self::DEFAULT_STORE;

        $stores = $this->config->get('indexnow.stores');
        $stores = \is_array($stores) ? $stores : [];

        $settings = $stores[$name] ?? null;

        if (! \is_array($settings)) {
            throw IndexNowConfigurationException::unknownStore($name, array_keys($stores));
        }

        $driver = $settings['driver'] ?? $name;
        $driver = \is_string($driver) ? $driver : '';

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
            $cache->store(ConfigValues::toString($settings['store'] ?? null)),
            ConfigValues::toString($settings['key'] ?? null) ?? self::DEFAULT_CACHE_KEY,
            ConfigValues::toPositiveInt($settings['ttl'] ?? null, self::DEFAULT_CACHE_TTL),
            ConfigValues::toPositiveInt($settings['lock_seconds'] ?? null, self::DEFAULT_LOCK_SECONDS),
        );
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function makeDatabaseStore(array $settings): DatabaseUrlStore
    {
        $connections = $this->container->make(ConnectionResolverInterface::class);

        return new DatabaseUrlStore(
            $connections->connection(ConfigValues::toString($settings['connection'] ?? null)),
            ConfigValues::toString($settings['table'] ?? null) ?? self::DEFAULT_TABLE,
        );
    }
}
