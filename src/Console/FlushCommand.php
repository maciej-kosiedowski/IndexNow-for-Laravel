<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Console;

use Illuminate\Console\Command;
use SlimAD\IndexNow\Laravel\IndexNowManager;

final class FlushCommand extends Command
{
    /** @var string */
    protected $signature = 'indexnow:flush
                            {--sync : Submit inline instead of dispatching a queued job}';

    /** @var string */
    protected $description = 'Submit every pending URL to the configured IndexNow endpoints';

    public function handle(IndexNowManager $manager): int
    {
        if (! $manager->isEnabled()) {
            $this->components->warn('IndexNow is disabled (indexnow.enabled); nothing was submitted.');

            return self::SUCCESS;
        }

        $pending = $manager->pending();

        if ($pending === 0) {
            $this->components->info('No pending URLs.');

            return self::SUCCESS;
        }

        if ($this->option('sync') !== true && $manager->dispatchFlush()) {
            $this->components->info(\sprintf('Dispatched a job to submit %d pending URL(s).', $pending));

            return self::SUCCESS;
        }

        $result = $manager->flush();

        if ($result->discardedUrls > 0) {
            $this->components->warn(\sprintf(
                'Discarded %d queued URL(s) that do not belong to the configured host.',
                $result->discardedUrls,
            ));
        }

        if ($result->hasFailures()) {
            foreach ($result->failures as $failure) {
                $this->components->error($failure->getMessage());
            }

            $this->components->warn(\sprintf(
                '%d URL(s) stay queued and will be retried on the next run.',
                $result->submittedUrls,
            ));

            return self::FAILURE;
        }

        $this->components->info(\sprintf('Submitted %d URL(s).', $result->submittedUrls));

        return self::SUCCESS;
    }
}
