<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Concerns;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use SlimAD\IndexNow\Laravel\Contracts\ProvidesIndexNowUrls;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Laravel\IndexNowManager;

/**
 * Queues a model's public URLs whenever it is saved or deleted.
 *
 * The model has to implement {@see ProvidesIndexNowUrls}; return an empty
 * iterable from `indexNowUrls()` to skip a model (a draft, a soft-deleted
 * record, ...).
 *
 * @mixin Model
 */
trait SubmitsToIndexNow
{
    public static function bootSubmitsToIndexNow(): void
    {
        $queue = static function (Model $model): void {
            /** @var static $model */
            $model->submitToIndexNow();
        };

        static::saved($queue);
        static::deleted($queue);
    }

    public function submitToIndexNow(): int
    {
        if (! $this instanceof ProvidesIndexNowUrls) {
            throw IndexNowConfigurationException::modelDoesNotProvideUrls(static::class);
        }

        return Container::getInstance()
            ->make(IndexNowManager::class)
            ->submitMany($this->indexNowUrls());
    }
}
