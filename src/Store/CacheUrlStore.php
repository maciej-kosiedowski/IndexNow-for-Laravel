<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Store;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use SlimAD\IndexNow\Exception\InvalidUrlException;
use SlimAD\IndexNow\Store\UrlStore;
use SlimAD\IndexNow\ValueObject\Url;

/**
 * Keeps pending URLs in one cache entry.
 *
 * Mutations are guarded by a cache lock when the underlying store supports one
 * (Redis, Memcached, DynamoDB, database), so concurrent web requests do not lose
 * each other's writes. Stores without locking (file, array) fall back to a plain
 * read-modify-write.
 *
 * A cache flush loses the queue; use the database store when that matters.
 */
final class CacheUrlStore implements UrlStore
{
    public function __construct(
        private readonly Repository $cache,
        public readonly string $key,
        public readonly int $ttl,
        public readonly int $lockSeconds,
    ) {}

    public function add(Url $url): void
    {
        $this->mutate(static function (array $urls) use ($url): array {
            $urls[$url->value] = $url;

            return $urls;
        });
    }

    /**
     * @return list<Url>
     */
    public function all(): array
    {
        return array_values($this->read());
    }

    public function remove(Url $url): void
    {
        $this->mutate(static function (array $urls) use ($url): array {
            unset($urls[$url->value]);

            return $urls;
        });
    }

    public function clear(): void
    {
        $this->cache->forget($this->key);
    }

    public function count(): int
    {
        return \count($this->read());
    }

    /**
     * @return array<string, Url>
     */
    private function read(): array
    {
        $stored = $this->cache->get($this->key);

        if (! \is_array($stored)) {
            return [];
        }

        $urls = [];

        foreach ($stored as $value) {
            if (! \is_string($value)) {
                continue;
            }

            try {
                $url = new Url($value);
            } catch (InvalidUrlException) {
                continue;
            }

            $urls[$url->value] = $url;
        }

        return $urls;
    }

    /**
     * @param  callable(array<string, Url>): array<string, Url>  $mutation
     */
    private function mutate(callable $mutation): void
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            $this->write($mutation($this->read()));

            return;
        }

        $store->lock($this->key.':lock', $this->lockSeconds)->block(
            $this->lockSeconds,
            function () use ($mutation): void {
                $this->write($mutation($this->read()));
            },
        );
    }

    /**
     * @param  array<string, Url>  $urls
     */
    private function write(array $urls): void
    {
        if ($urls === []) {
            $this->cache->forget($this->key);

            return;
        }

        $this->cache->put(
            $this->key,
            array_values(array_map(static fn (Url $url): string => $url->value, $urls)),
            $this->ttl,
        );
    }
}
