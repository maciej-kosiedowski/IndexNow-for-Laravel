<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Support;

use Illuminate\Contracts\Cache\Store;
use SlimAD\IndexNow\Laravel\Store\CacheUrlStore;

/**
 * A cache store that deliberately does not implement LockProvider, so the
 * lock-less branch of {@see CacheUrlStore} can be exercised (the built-in array
 * store does support locking).
 */
final class NonLockingCacheStore implements Store
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function get($key): mixed
    {
        return $this->items[$key] ?? null;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function put($key, $value, $seconds): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, $seconds): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1): bool
    {
        return false;
    }

    public function decrement($key, $value = 1): bool
    {
        return false;
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Laravel 13 added this to the Store contract; the double keeps no TTLs, so
     * there is nothing to move but the answer still has to be truthful.
     *
     * @param  string  $key
     * @param  int  $seconds
     */
    public function touch($key, $seconds): bool
    {
        return \array_key_exists($key, $this->items);
    }

    public function forget($key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function flush(): bool
    {
        $this->items = [];

        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }
}
