<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Exceptions;

use SlimAD\IndexNow\Exception\IndexNowException;

final class IndexNowConfigurationException extends IndexNowException
{
    public static function missingValue(string $key, string $environmentVariable): self
    {
        return new self(\sprintf(
            'IndexNow is not configured: "%s" is empty. Set %s in your .env file or publish and edit config/indexnow.php.',
            $key,
            $environmentVariable,
        ));
    }

    public static function noEngines(): self
    {
        return new self(
            'IndexNow is not configured: "indexnow.engines" is empty. Keep at least the default "indexnow" entry.',
        );
    }

    public static function missingEngineEndpoint(string $engine): self
    {
        return new self(\sprintf(
            'IndexNow engine "%s" has no "endpoint". Every entry in "indexnow.engines" needs one.',
            $engine,
        ));
    }

    /**
     * @param  list<int|string>  $available
     */
    public static function unknownStore(string $name, array $available): self
    {
        return new self(\sprintf(
            'IndexNow store "%s" is not defined in "indexnow.stores". Available: %s.',
            $name,
            $available === [] ? '<none>' : implode(', ', $available),
        ));
    }

    public static function unsupportedStoreDriver(string $store, string $driver): self
    {
        return new self(\sprintf(
            'IndexNow store "%s" uses the unsupported driver "%s". Supported drivers are: array, cache, database.',
            $store,
            $driver,
        ));
    }

    public static function modelDoesNotProvideUrls(string $model): self
    {
        return new self(\sprintf(
            'Model %s uses the SubmitsToIndexNow trait but does not implement %s.',
            $model,
            \SlimAD\IndexNow\Laravel\Contracts\ProvidesIndexNowUrls::class,
        ));
    }
}
