<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SlimAD\IndexNow\Laravel\IndexNowManager;

/**
 * @method static bool isEnabled()
 * @method static bool usesQueue()
 * @method static int submit(\SlimAD\IndexNow\ValueObject\Url|string ...$urls)
 * @method static int submitMany(iterable<\SlimAD\IndexNow\ValueObject\Url|string> $urls)
 * @method static int pending()
 * @method static void clear()
 * @method static \SlimAD\IndexNow\Store\UrlStore store()
 * @method static \SlimAD\IndexNow\Job\SubmitJobResult flush()
 * @method static bool dispatchFlush()
 *
 * @see IndexNowManager
 */
final class IndexNow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IndexNowManager::class;
    }
}
