<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use SlimAD\IndexNow\Exception\IndexNowException;
use SlimAD\IndexNow\Laravel\Config\IndexNowConfigFactory;
use SlimAD\IndexNow\Laravel\IndexNowManager;

final class StatusCommand extends Command
{
    /** @var string */
    protected $signature = 'indexnow:status';

    /** @var string */
    protected $description = 'Show the current IndexNow configuration and how many URLs are pending';

    public function handle(IndexNowManager $manager, Repository $config, Container $container): int
    {
        $store = $config->get('indexnow.store');

        /** @var list<array{string, string}> $rows */
        $rows = [
            ['Enabled', $manager->isEnabled() ? 'yes' : 'no'],
            ['Store', \is_string($store) ? $store : '<not set>'],
            ['Pending URLs', (string) $manager->pending()],
            ['Queue', $manager->usesQueue() ? 'yes' : 'no (submits inline)'],
        ];

        try {
            $indexNowConfig = $container->make(IndexNowConfigFactory::class)->make();
        } catch (IndexNowException $exception) {
            $this->table(['Setting', 'Value'], $rows);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows[] = ['Host', $indexNowConfig->host->value];

        foreach ($indexNowConfig->engines as $engine) {
            $rows[] = [\sprintf('Engine: %s', $engine->name), $engine->endpoint];
            $rows[] = [\sprintf('Key location: %s', $engine->name), $engine->keyLocation->value()];
        }

        $this->table(['Setting', 'Value'], $rows);

        return self::SUCCESS;
    }
}
