<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

/**
 * How the package talks to an IndexNow endpoint.
 */
final readonly class HttpOptions
{
    public const DEFAULT_TIMEOUT = 10;

    public const DEFAULT_CONNECT_TIMEOUT = 5;

    public const DEFAULT_RETRIES = 3;

    public const DEFAULT_RETRY_DELAY = 250;

    public const DEFAULT_USER_AGENT = 'slimad/indexnow-laravel';

    public function __construct(
        public int $timeout,
        public int $connectTimeout,
        public int $retries,
        public int $retryDelay,
        public string $userAgent,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        return new self(
            (int) $config->get('indexnow.http.timeout') ?: self::DEFAULT_TIMEOUT,
            (int) $config->get('indexnow.http.connect_timeout') ?: self::DEFAULT_CONNECT_TIMEOUT,
            max(1, (int) ($config->get('indexnow.http.retries') ?? self::DEFAULT_RETRIES)),
            (int) ($config->get('indexnow.http.retry_delay') ?? self::DEFAULT_RETRY_DELAY),
            Str::squish((string) $config->get('indexnow.http.user_agent')) ?: self::DEFAULT_USER_AGENT,
        );
    }
}
