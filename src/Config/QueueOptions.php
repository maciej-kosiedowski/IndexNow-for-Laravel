<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;

/**
 * How the package hands submissions over to a queue worker.
 */
final class QueueOptions
{
    public const DEFAULT_TRIES = 3;

    /** @var list<int> */
    public const DEFAULT_BACKOFF = [60, 300, 900];

    /**
     * @param  list<int>  $backoff
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $connection,
        public readonly ?string $queue,
        public readonly int $tries,
        public readonly array $backoff,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        return new self(
            ConfigValues::bool($config, 'indexnow.queue.enabled', true),
            ConfigValues::string($config, 'indexnow.queue.connection'),
            ConfigValues::string($config, 'indexnow.queue.queue'),
            max(1, ConfigValues::int($config, 'indexnow.queue.tries', self::DEFAULT_TRIES)),
            ConfigValues::intList($config, 'indexnow.queue.backoff', self::DEFAULT_BACKOFF),
        );
    }
}
