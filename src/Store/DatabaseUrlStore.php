<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Store;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use SlimAD\IndexNow\Exception\InvalidUrlException;
use SlimAD\IndexNow\Store\UrlStore;
use SlimAD\IndexNow\ValueObject\Url;

/**
 * Keeps pending URLs in a database table so they survive deploys, cache flushes
 * and worker restarts.
 *
 * URLs are deduplicated on a SHA-256 hash rather than on the URL itself: MySQL
 * caps a unique index at 3072 bytes, which is not enough for a long URL column.
 */
final readonly class DatabaseUrlStore implements UrlStore
{
    public function __construct(
        private ConnectionInterface $connection,
        public string $table,
    ) {}

    public function add(Url $url): void
    {
        $this->query()->insertOrIgnore([
            'url_hash' => self::hash($url),
            'url' => $url->value,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @return list<Url>
     */
    public function all(): array
    {
        $urls = [];

        /** @var mixed $value */
        foreach ($this->query()->orderBy('id')->pluck('url') as $value) {
            if (! \is_string($value)) {
                continue;
            }

            try {
                $urls[] = new Url($value);
            } catch (InvalidUrlException) {
                continue;
            }
        }

        return $urls;
    }

    public function remove(Url $url): void
    {
        $this->query()->where('url_hash', self::hash($url))->delete();
    }

    public function clear(): void
    {
        $this->query()->delete();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    private static function hash(Url $url): string
    {
        return hash('sha256', $url->value);
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table);
    }
}
