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

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $uniqueFor;

    /**
     * @param  list<int>  $backoff
     */
    public function __construct(int $tries = 3, array $backoff = [60, 300, 900], int $uniqueFor = 300)
    {
        $this->tries = $tries;
        $this->backoff = $backoff;
        $this->uniqueFor = $uniqueFor;
    }

    public function uniqueId(): string
    {
        return 'indexnow-flush';
    }

    public function handle(IndexNowManager $manager): void
    {
        $manager->flush();
    }
}
