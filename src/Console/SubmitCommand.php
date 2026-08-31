<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Console;

use Illuminate\Console\Command;
use SlimAD\IndexNow\Exception\InvalidUrlException;
use SlimAD\IndexNow\Laravel\IndexNowManager;

final class SubmitCommand extends Command
{
    /** @var string */
    protected $signature = 'indexnow:submit
                            {url* : One or more absolute http/https URLs}
                            {--flush : Submit immediately instead of waiting for the next flush}';

    /** @var string */
    protected $description = 'Queue one or more URLs for submission to IndexNow';

    public function handle(IndexNowManager $manager): int
    {
        if (! $manager->isEnabled()) {
            $this->components->warn('IndexNow is disabled (indexnow.enabled); nothing was queued.');

            return self::SUCCESS;
        }

        /** @var list<string> $urls */
        $urls = (array) $this->argument('url');

        try {
            $queued = $manager->submitMany($urls);
        } catch (InvalidUrlException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $this->components->info(\sprintf('Queued %d URL(s).', $queued));

        if ($this->option('flush') !== true) {
            return self::SUCCESS;
        }

        return $this->call(FlushCommand::class, ['--sync' => true]);
    }
}
