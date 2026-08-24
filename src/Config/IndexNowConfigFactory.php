<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;
use SlimAD\IndexNow\Config\IndexNowConfig;
use SlimAD\IndexNow\Config\SearchEngine;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\ValueObject\Host;
use SlimAD\IndexNow\ValueObject\Key;
use SlimAD\IndexNow\ValueObject\KeyLocation;

/**
 * Turns the `config/indexnow.php` array into the value objects the core package
 * works with, failing with an actionable message when something is missing.
 */
final class IndexNowConfigFactory
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @throws \SlimAD\IndexNow\Exception\IndexNowException when the configuration is incomplete or invalid
     */
    public function make(): IndexNowConfig
    {
        $host = new Host($this->required('indexnow.host', 'INDEXNOW_HOST'));
        $key = $this->required('indexnow.key', 'INDEXNOW_KEY');
        $keyLocation = $this->optional('indexnow.key_location')
            ?? self::defaultKeyLocation($host->value, $key);

        $engines = $this->config->get('indexnow.engines');

        if (! \is_array($engines) || $engines === []) {
            throw IndexNowConfigurationException::noEngines();
        }

        $configured = [];

        foreach ($engines as $name => $engine) {
            $name = (string) $name;
            $engine = \is_array($engine) ? $engine : [];

            $endpoint = self::stringOrNull($engine['endpoint'] ?? null);

            if ($endpoint === null) {
                throw IndexNowConfigurationException::missingEngineEndpoint($name);
            }

            $engineKey = self::stringOrNull($engine['key'] ?? null) ?? $key;

            $configured[] = new SearchEngine(
                $name,
                $endpoint,
                new Key($engineKey),
                KeyLocation::fromString(
                    self::stringOrNull($engine['key_location'] ?? null)
                        ?? ($engineKey === $key
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
        $value = $this->optional($key);

        if ($value === null) {
            throw IndexNowConfigurationException::missingValue($key, $environmentVariable);
        }

        return $value;
    }

    private function optional(string $key): ?string
    {
        return self::stringOrNull($this->config->get($key));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
