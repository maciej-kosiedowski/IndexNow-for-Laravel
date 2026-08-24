<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Support;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Lock;

/**
 * An array cache store that records every lock the package asks for, so tests
 * can prove that concurrent writes really are serialised.
 */
final class RecordingLockCacheStore extends ArrayStore
{
    /** @var list<array{name: string, seconds: int}> */
    public array $requestedLocks = [];

    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        $this->requestedLocks[] = ['name' => (string) $name, 'seconds' => (int) $seconds];

        return parent::lock($name, $seconds, $owner);
    }
}
