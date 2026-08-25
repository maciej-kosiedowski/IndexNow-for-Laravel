<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * How the package hands submissions over to a queue worker.
 */
final readonly class QueueOptions
{
    public const DEFAULT_TRIES = 3;

    /** @var list<int> */
    public const DEFAULT_BACKOFF = [60, 300, 900];

    /**
     * @param  list<int>  $backoff
     */
    public function __construct(
        public bool $enabled,
        public ?string $connection,
        public ?string $queue,
        public int $tries,
        public array $backoff,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        return new self(
            (bool) ($config->get('indexnow.queue.enabled') ?? true),
            Str::squish((string) $config->get('indexnow.queue.connection')) ?: null,
            Str::squish((string) $config->get('indexnow.queue.queue')) ?: null,
            max(1, (int) ($config->get('indexnow.queue.tries') ?? self::DEFAULT_TRIES)),
            self::backoff($config),
        );
    }

    /**
     * Seconds between attempts. Anything that is not a non-negative integer is
     * dropped, because the whole list travels in the queue payload.
     *
     * @return list<int>
     */
    private static function backoff(Repository $config): array
    {
        $seconds = array_values(array_filter(
            Arr::wrap($config->get('indexnow.queue.backoff')),
            static fn (mixed $entry): bool => \is_int($entry) && $entry >= 0,
        ));

        return $seconds === [] ? self::DEFAULT_BACKOFF : $seconds;
    }
}
