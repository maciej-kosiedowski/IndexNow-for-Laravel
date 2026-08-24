<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;
use SlimAD\IndexNow\Laravel\Facades\IndexNow;
use SlimAD\IndexNow\Laravel\IndexNowServiceProvider;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [IndexNowServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['IndexNow' => IndexNow::class];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app->make(Repository::class);

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');

        $config->set('database.default', 'testing');

        $config->set('indexnow.host', 'example.com');
        $config->set('indexnow.key', 'abcdef0123456789abcdef0123456789');
        $config->set('indexnow.store', 'array');
        $config->set('indexnow.queue.enabled', false);
        $config->set('indexnow.schedule.enabled', false);
        $config->set('indexnow.key_route.enabled', false);
        $config->set('indexnow.http.retries', 1);
        $config->set('indexnow.http.retry_delay', 0);
    }

    protected function container(): Application
    {
        $app = $this->app;

        if (! $app instanceof Application) {
            throw new RuntimeException('The Testbench application has not been created yet.');
        }

        return $app;
    }

    protected function config(): Repository
    {
        return $this->container()->make(Repository::class);
    }

    /**
     * `$this->artisan()` is typed as PendingCommand|int; tests always run with
     * a mocked console, so narrow it once here instead of in every assertion.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new RuntimeException('Artisan did not return a pending command.');
        }

        return $pending;
    }

    /**
     * Runs an Artisan command and captures everything it printed.
     *
     * `$this->artisan()` can only assert on a single chunk of table output, so
     * commands that render tables are asserted against this buffer instead.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{status: int, output: string}
     */
    protected function runCommand(string $command, array $parameters = []): array
    {
        $output = new BufferedOutput;

        $status = $this->container()->make(Kernel::class)->call($command, $parameters, $output);

        return ['status' => $status, 'output' => $output->fetch()];
    }
}
