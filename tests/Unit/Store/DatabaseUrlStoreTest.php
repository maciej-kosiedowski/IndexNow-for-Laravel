<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Store;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SlimAD\IndexNow\Laravel\Store\DatabaseUrlStore;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\ValueObject\Url;

final class DatabaseUrlStoreTest extends TestCase
{
    private DatabaseUrlStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(\dirname(__DIR__, 3).'/database/migrations');

        $this->store = new DatabaseUrlStore($this->connection(), 'indexnow_urls');

        self::assertSame('indexnow_urls', $this->store->table);
    }

    private function connection(): ConnectionInterface
    {
        return $this->container()->make(ConnectionResolverInterface::class)->connection();
    }

    public function test_it_starts_empty(): void
    {
        self::assertSame(0, $this->store->count());
        self::assertSame([], $this->store->all());
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

    public function test_it_stores_a_hash_so_long_urls_stay_indexable(): void
    {
        $url = new Url('https://example.com/'.str_repeat('a', 900));

        $this->store->add($url);

        self::assertSame(1, $this->store->count());
        self::assertSame($url->value, $this->store->all()[0]->value);
        self::assertSame(
            hash('sha256', $url->value),
            $this->connection()->table('indexnow_urls')->value('url_hash'),
        );
    }

    public function test_it_removes_a_single_url(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));

        $this->store->remove(new Url('https://example.com/a'));

        self::assertSame(1, $this->store->count());
        self::assertSame('https://example.com/b', $this->store->all()[0]->value);
    }

    public function test_removing_an_unknown_url_is_a_noop(): void
    {
        $this->store->add(new Url('https://example.com/a'));

        $this->store->remove(new Url('https://example.com/missing'));

        self::assertSame(1, $this->store->count());
    }

    public function test_clear_removes_everything(): void
    {
        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));

        $this->store->clear();

        self::assertSame(0, $this->store->count());
        self::assertSame([], $this->store->all());
    }

    public function test_it_skips_rows_that_are_not_usable_urls(): void
    {
        // The broken row goes in first, so a reader that stops at the first bad
        // row instead of skipping it would lose everything behind it.
        $this->connection()->table('indexnow_urls')->insert([
            'url_hash' => hash('sha256', 'broken'),
            'url' => 'not-a-url',
            'created_at' => null,
        ]);

        $this->store->add(new Url('https://example.com/a'));
        $this->store->add(new Url('https://example.com/b'));

        self::assertSame(
            ['https://example.com/a', 'https://example.com/b'],
            array_map(static fn (Url $url): string => $url->value, $this->store->all()),
        );
    }

    public function test_it_skips_rows_that_do_not_even_hold_a_string(): void
    {
        // A table pointed at by mistake can hand back anything at all; a queue
        // that explodes on read would never drain again.
        Schema::create('legacy_urls', static function (Blueprint $table): void {
            $table->id();
            $table->string('url_hash', 64);
            $table->integer('url');
            $table->timestamp('created_at')->nullable();
        });

        $this->connection()->table('legacy_urls')->insert([
            ['url_hash' => hash('sha256', 'numeric'), 'url' => 42, 'created_at' => null],
            ['url_hash' => hash('sha256', 'url'), 'url' => 'https://example.com/a', 'created_at' => null],
        ]);

        $store = new DatabaseUrlStore($this->connection(), 'legacy_urls');

        self::assertSame(
            ['https://example.com/a'],
            array_map(static fn (Url $url): string => $url->value, $store->all()),
        );
        self::assertSame(2, $store->count());
    }
}
