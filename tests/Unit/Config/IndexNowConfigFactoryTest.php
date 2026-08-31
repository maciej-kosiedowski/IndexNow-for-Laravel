<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Config;

use Illuminate\Config\Repository;
use SlimAD\IndexNow\Config\SearchEngine;
use SlimAD\IndexNow\Laravel\Config\IndexNowConfigFactory;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class IndexNowConfigFactoryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function factory(array $overrides = []): IndexNowConfigFactory
    {
        $config = [
            'host' => 'example.com',
            'key' => 'abcdef0123456789abcdef0123456789',
            'key_location' => null,
            'engines' => [
                'indexnow' => ['endpoint' => 'https://api.indexnow.org/indexnow'],
            ],
        ];

        return new IndexNowConfigFactory(new Repository(['indexnow' => array_replace($config, $overrides)]));
    }

    public function test_it_builds_the_core_configuration(): void
    {
        $config = $this->factory()->make();

        self::assertSame('example.com', $config->host->value);
        self::assertSame(['indexnow'], array_keys($config->engines));

        $engine = $config->engine('indexnow');

        self::assertNotNull($engine);
        self::assertSame('https://api.indexnow.org/indexnow', $engine->endpoint);
        self::assertSame('abcdef0123456789abcdef0123456789', $engine->key->value);
    }

    public function test_it_derives_the_key_location_from_host_and_key(): void
    {
        $engine = $this->factory()->make()->engine('indexnow');

        self::assertNotNull($engine);
        self::assertSame(
            'https://example.com/abcdef0123456789abcdef0123456789.txt',
            $engine->keyLocation->value(),
        );
    }

    public function test_an_explicit_key_location_wins(): void
    {
        $engine = $this->factory(['key_location' => 'https://example.com/seo/key.txt'])->make()->engine('indexnow');

        self::assertNotNull($engine);
        self::assertSame('https://example.com/seo/key.txt', $engine->keyLocation->value());
    }

    public function test_an_engine_can_override_the_key_and_its_location(): void
    {
        $config = $this->factory([
            'engines' => [
                'indexnow' => ['endpoint' => 'https://api.indexnow.org/indexnow'],
                'yandex' => [
                    'endpoint' => 'https://yandex.com/indexnow',
                    'key' => '0123456789abcdef',
                ],
                'bing' => [
                    'endpoint' => 'https://www.bing.com/indexnow',
                    'key' => 'fedcba9876543210',
                    'key_location' => 'https://example.com/bing.txt',
                ],
            ],
        ])->make();

        $yandex = $config->engine('yandex');
        $bing = $config->engine('bing');

        self::assertNotNull($yandex);
        self::assertNotNull($bing);
        self::assertSame('0123456789abcdef', $yandex->key->value);
        self::assertSame(
            'https://example.com/0123456789abcdef.txt',
            $yandex->keyLocation->value(),
            'an engine with its own key gets its own derived key location',
        );
        self::assertSame('https://example.com/bing.txt', $bing->keyLocation->value());
    }

    public function test_engines_defined_as_a_list_get_numeric_names(): void
    {
        $config = $this->factory([
            'engines' => [
                ['endpoint' => 'https://api.indexnow.org/indexnow'],
                ['endpoint' => 'https://www.bing.com/indexnow'],
            ],
        ])->make();

        self::assertSame(
            ['0', '1'],
            array_map(static fn (SearchEngine $engine): string => $engine->name, array_values($config->engines)),
            'a list of engines has to end up with string names',
        );
        self::assertNotNull($config->engine('0'));
    }

    public function test_it_rejects_a_missing_host(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('"indexnow.host" is empty. Set INDEXNOW_HOST');

        $this->factory(['host' => null])->make();
    }

    public function test_it_rejects_a_blank_host(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('INDEXNOW_HOST');

        $this->factory(['host' => '   '])->make();
    }

    public function test_it_rejects_a_missing_key(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('"indexnow.key" is empty. Set INDEXNOW_KEY');

        $this->factory(['key' => null])->make();
    }

    public function test_it_rejects_an_empty_engine_list(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('"indexnow.engines" is empty');

        $this->factory(['engines' => []])->make();
    }

    public function test_it_rejects_engines_that_are_not_an_array(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('"indexnow.engines" is empty');

        $this->factory(['engines' => 'indexnow'])->make();
    }

    public function test_it_rejects_an_engine_without_an_endpoint(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('IndexNow engine "bing" has no "endpoint"');

        $this->factory(['engines' => ['bing' => ['key' => 'abcdef0123456789']]])->make();
    }

    public function test_it_rejects_an_engine_defined_as_a_scalar(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('IndexNow engine "bing" has no "endpoint"');

        $this->factory(['engines' => ['bing' => 'https://www.bing.com/indexnow']])->make();
    }

    public function test_default_key_location_is_public(): void
    {
        self::assertSame(
            'https://example.com/key123.txt',
            IndexNowConfigFactory::defaultKeyLocation('example.com', 'key123'),
        );
    }
}
