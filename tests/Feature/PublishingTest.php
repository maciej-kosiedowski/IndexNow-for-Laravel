<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use SlimAD\IndexNow\Laravel\IndexNowServiceProvider;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class PublishingTest extends TestCase
{
    public function test_the_configuration_can_be_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(IndexNowServiceProvider::class, 'indexnow-config');

        self::assertCount(1, $paths);
        self::assertSame(['indexnow.php'], array_map('basename', array_keys($paths)));
        self::assertSame(['indexnow.php'], array_map('basename', array_values($paths)));
    }

    public function test_the_migrations_can_be_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(IndexNowServiceProvider::class, 'indexnow-migrations');

        self::assertCount(1, $paths);
        self::assertSame(['migrations'], array_map('basename', array_keys($paths)));
        self::assertSame(['migrations'], array_map('basename', array_values($paths)));
    }

    public function test_everything_is_publishable_under_one_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(IndexNowServiceProvider::class, 'indexnow');

        self::assertCount(2, $paths);
    }

    public function test_the_published_file_is_the_one_the_provider_merges(): void
    {
        $paths = ServiceProvider::pathsToPublish(IndexNowServiceProvider::class, 'indexnow-config');
        $source = array_key_first($paths);

        self::assertIsString($source);
        self::assertFileExists($source);

        /** @var array<string, mixed> $published */
        $published = require $source;

        /** @var array<string, mixed> $merged */
        $merged = $this->config()->get('indexnow');

        self::assertSame(
            array_keys($published),
            array_keys($merged),
            'a key that only exists in one of the two would silently fall back to a hard-coded default',
        );
    }

    public function test_the_migration_directory_actually_contains_the_migration(): void
    {
        $paths = ServiceProvider::pathsToPublish(IndexNowServiceProvider::class, 'indexnow-migrations');
        $source = array_key_first($paths);

        self::assertIsString($source);
        self::assertDirectoryExists($source);
        self::assertNotEmpty(glob($source.'/*_create_indexnow_urls_table.php'));
    }
}
