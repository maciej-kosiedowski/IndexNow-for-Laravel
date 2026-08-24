<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;

/**
 * How the package talks to an IndexNow endpoint.
 */
final class HttpOptions
{
    public const DEFAULT_TIMEOUT = 10;

    public const DEFAULT_CONNECT_TIMEOUT = 5;

    public const DEFAULT_RETRIES = 3;

    public const DEFAULT_RETRY_DELAY = 250;

    public const DEFAULT_USER_AGENT = 'slimad-indexnow-laravel (+https://github.com/maciej-kosiedowski/IndexNow-for-Laravel)';

    public function __construct(
        public readonly int $timeout,
        public readonly int $connectTimeout,
        public readonly int $retries,
        public readonly int $retryDelay,
        public readonly string $userAgent,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        return new self(
            ConfigValues::int($config, 'indexnow.http.timeout', self::DEFAULT_TIMEOUT),
            ConfigValues::int($config, 'indexnow.http.connect_timeout', self::DEFAULT_CONNECT_TIMEOUT),
            max(1, ConfigValues::int($config, 'indexnow.http.retries', self::DEFAULT_RETRIES)),
            ConfigValues::int($config, 'indexnow.http.retry_delay', self::DEFAULT_RETRY_DELAY),
            ConfigValues::string($config, 'indexnow.http.user_agent') ?? self::DEFAULT_USER_AGENT,
        );
    }
}
