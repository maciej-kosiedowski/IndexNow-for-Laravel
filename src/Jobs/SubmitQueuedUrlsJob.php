<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use SlimAD\IndexNow\Laravel\IndexNowManager;

/**
 * Drains the pending URL store on a queue worker.
 *
 * The job is unique so a slow submission cannot overlap with the next scheduled
 * run and submit the same batch twice.
 */
final class SubmitQueuedUrlsJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  list<int>  $backoff
     */
    public function __construct(
        public readonly int $tries = 3,
        public readonly array $backoff = [60, 300, 900],
        public readonly int $uniqueFor = 300,
    ) {}

    public function uniqueId(): string
    {
        return 'indexnow-flush';
    }

    public function handle(IndexNowManager $manager): void
    {
        $manager->flush();
    }
}
