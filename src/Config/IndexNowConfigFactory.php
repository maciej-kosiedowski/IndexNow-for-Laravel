<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SlimAD\IndexNow\Config\IndexNowConfig;
use SlimAD\IndexNow\Config\SearchEngine;
use SlimAD\IndexNow\Exception\IndexNowException;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\ValueObject\Host;
use SlimAD\IndexNow\ValueObject\Key;
use SlimAD\IndexNow\ValueObject\KeyLocation;

/**
 * Turns the `config/indexnow.php` array into the value objects the core package
 * works with, failing with an actionable message when something is missing.
 */
final readonly class IndexNowConfigFactory
{
    public function __construct(private Repository $config) {}

    /**
     * @throws IndexNowException when the configuration is incomplete or invalid
     */
    public function make(): IndexNowConfig
    {
        $host = new Host($this->required('indexnow.host', 'INDEXNOW_HOST'));
        $key = $this->required('indexnow.key', 'INDEXNOW_KEY');
        $keyLocation = $this->text('indexnow.key_location')
            ?? self::defaultKeyLocation($host->value, $key);

        $engines = $this->config->get('indexnow.engines');

        if (! \is_array($engines) || $engines === []) {
            throw IndexNowConfigurationException::noEngines();
        }

        $configured = [];

        foreach ($engines as $name => $engine) {
            $name = (string) $name;
            $engine = \is_array($engine) ? $engine : [];

            $endpoint = Str::squish((string) Arr::get($engine, 'endpoint'));

            if ($endpoint === '') {
                throw IndexNowConfigurationException::missingEngineEndpoint($name);
            }

            $engineKey = Str::squish((string) Arr::get($engine, 'key')) ?: $key;

            $configured[] = new SearchEngine(
                $name,
                $endpoint,
                new Key($engineKey),
                KeyLocation::fromString(
                    Str::squish((string) Arr::get($engine, 'key_location'))
                        ?: ($engineKey === $key
                            ? $keyLocation
                            : self::defaultKeyLocation($host->value, $engineKey)),
                ),
            );
        }

        return new IndexNowConfig($host, $configured);
    }

    /**
     * The location the built-in key route serves the key from.
     */
    public static function defaultKeyLocation(string $host, string $key): string
    {
        return \sprintf('https://%s/%s.txt', $host, $key);
    }

    private function required(string $key, string $environmentVariable): string
    {
        return $this->text($key)
            ?? throw IndexNowConfigurationException::missingValue($key, $environmentVariable);
    }

    /**
     * A trimmed, non-blank configuration string, or null when it is not set.
     */
    private function text(string $key): ?string
    {
        return Str::squish((string) $this->config->get($key)) ?: null;
    }
}
