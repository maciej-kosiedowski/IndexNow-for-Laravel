<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Store;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use SlimAD\IndexNow\Laravel\Store\CacheUrlStore;
use SlimAD\IndexNow\Laravel\Tests\Support\NonLockingCacheStore;
use SlimAD\IndexNow\Laravel\Tests\Support\RecordingLockCacheStore;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\ValueObject\Url;

final class CacheUrlStoreTest extends TestCase
{
    private Repository $cache;

    private CacheUrlStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Repository(new ArrayStore);
        $this->store = new CacheUrlStore($this->cache, 'indexnow:pending', 60, 5);
    }

    public function test_it_starts_empty(): void
    {
        self::assertSame(0, $this->store->count());
        self::assertSame([], $this->store->all());
    }

    public function test_it_exposes_its_settings(): void
    {
        self::assertSame('indexnow:pending', $this->store->key);
        self::assertSame(60, $this->store->ttl);
        self::assertSame(5, $this->store->lockSeconds);
    }

    public function test_it_collects_unique_urls(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));
        $this->store->add(new Url('https://example.com/a'));

        self::assertSame(2, $this->store->count());
        self::assertSame(
            ['https://example.com/a', 'https://example.com/b'],
            array_map(static fn (Url $url): string => $url->value, $this->store->all()),
        );
    }

    public function test_it_removes_a_single_url(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));
        $this->store->add(new Url('https://example.com/c'));

        $this->store->remove(new Url('https://example.com/a'));

        self::assertSame(2, $this->store->count());
        self::assertSame(
            ['https://example.com/b', 'https://example.com/c'],
            array_map(static fn (Url $url): string => $url->value, $this->store->all()),
            'removing one URL must not drop the others',
        );
    }

    public function test_removing_an_unknown_url_is_a_noop(): void
    {
        $this->store->add(new Url('https://example.com/a'));

        $this->store->remove(new Url('https://example.com/missing'));

        self::assertSame(1, $this->store->count());
    }

    public function test_it_forgets_the_cache_entry_once_empty(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->remove(new Url('https://example.com/a'));

        self::assertNull($this->cache->get('indexnow:pending'));
        self::assertSame(0, $this->store->count());
    }

    public function test_clear_forgets_everything(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));

        $this->store->clear();

        self::assertNull($this->cache->get('indexnow:pending'));
        self::assertSame([], $this->store->all());
    }

    public function test_it_stores_plain_strings_in_the_cache(): void
    {
        $this->store->add(new Url('https://example.com/a'));

        self::assertSame(['https://example.com/a'], $this->cache->get('indexnow:pending'));
    }

    public function test_it_ignores_a_corrupted_cache_entry(): void
    {
        $this->cache->put('indexnow:pending', 'not-an-array', 60);

        self::assertSame([], $this->store->all());
        self::assertSame(0, $this->store->count());
    }

    public function test_it_skips_entries_that_are_not_usable_urls(): void
    {
        $this->cache->put('indexnow:pending', [42, 'not-a-url', 'https://example.com/a', 'https://example.com/b'], 60);

        self::assertSame(
            ['https://example.com/a', 'https://example.com/b'],
            array_map(static fn (Url $url): string => $url->value, $this->store->all()),
            'a broken entry must not hide the entries after it',
        );
    }

    public function test_every_mutation_takes_a_lock_when_the_store_supports_one(): void
    {
        $backing = new RecordingLockCacheStore;
        $store = new CacheUrlStore(new Repository($backing), 'indexnow:pending', 60, 7);

        $store->add(new Url('https://example.com/a'));
        $store->remove(new Url('https://example.com/a'));

        self::assertSame([
            ['name' => 'indexnow:pending:lock', 'seconds' => 7],
            ['name' => 'indexnow:pending:lock', 'seconds' => 7],
        ], $backing->requestedLocks);
    }

    public function test_reads_do_not_take_a_lock(): void
    {
        $backing = new RecordingLockCacheStore;
        $store = new CacheUrlStore(new Repository($backing), 'indexnow:pending', 60, 7);

        $store->all();
        $store->count();
        $store->clear();

        self::assertSame([], $backing->requestedLocks);
    }

    public function test_it_works_with_a_cache_store_that_cannot_lock(): void
    {
        $cache = new Repository(new NonLockingCacheStore);
        $store = new CacheUrlStore($cache, 'indexnow:pending', 60, 5);

        $store->add(new Url('https://example.com/a'));
        $store->add(new Url('https://example.com/b'));
        $store->add(new Url('https://example.com/c'));
        $store->remove(new Url('https://example.com/a'));

        self::assertSame(2, $store->count());
        self::assertSame(
            ['https://example.com/b', 'https://example.com/c'],
            array_map(static fn (Url $url): string => $url->value, $store->all()),
        );
    }
}
