<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use SlimAD\IndexNow\Client\IndexNowClient;
use SlimAD\IndexNow\Config\IndexNowConfig;
use SlimAD\IndexNow\Job\SubmitJob;
use SlimAD\IndexNow\Laravel\Client\LaravelHttpIndexNowClient;
use SlimAD\IndexNow\Laravel\Config\ConfigValues;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;
use SlimAD\IndexNow\Laravel\Config\IndexNowConfigFactory;
use SlimAD\IndexNow\Laravel\Config\QueueOptions;
use SlimAD\IndexNow\Laravel\Console\FlushCommand;
use SlimAD\IndexNow\Laravel\Console\GenerateKeyCommand;
use SlimAD\IndexNow\Laravel\Console\StatusCommand;
use SlimAD\IndexNow\Laravel\Console\SubmitCommand;
use SlimAD\IndexNow\Laravel\Http\Controllers\KeyFileController;
use SlimAD\IndexNow\Laravel\Store\UrlStoreFactory;
use SlimAD\IndexNow\Service\IndexNowService;
use SlimAD\IndexNow\Store\UrlStore;

final class IndexNowServiceProvider extends ServiceProvider
{
    public const DEFAULT_CRON = '*/5 * * * *';

    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'indexnow');

        $this->app->singleton(IndexNowConfigFactory::class, static fn (Container $app): IndexNowConfigFactory => new IndexNowConfigFactory(
            $app->make(Repository::class),
        ));

        $this->app->singleton(IndexNowConfig::class, static fn (Container $app): IndexNowConfig => $app
            ->make(IndexNowConfigFactory::class)
            ->make());

        $this->app->singleton(UrlStoreFactory::class, static fn (Container $app): UrlStoreFactory => new UrlStoreFactory(
            $app,
            $app->make(Repository::class),
        ));

        $this->app->singleton(UrlStore::class, static fn (Container $app): UrlStore => $app
            ->make(UrlStoreFactory::class)
            ->make());

        $this->app->singleton(HttpOptions::class, static fn (Container $app): HttpOptions => HttpOptions::fromConfig(
            $app->make(Repository::class),
        ));

        $this->app->singleton(QueueOptions::class, static fn (Container $app): QueueOptions => QueueOptions::fromConfig(
            $app->make(Repository::class),
        ));

        $this->app->singleton(IndexNowClient::class, static fn (Container $app): IndexNowClient => new LaravelHttpIndexNowClient(
            $app->make(HttpFactory::class),
            $app->make(HttpOptions::class),
        ));

        $this->app->singleton(IndexNowService::class, static fn (Container $app): IndexNowService => new IndexNowService(
            $app->make(UrlStore::class),
        ));

        $this->app->singleton(SubmitJob::class, static function (Container $app): SubmitJob {
            $batchSize = ConfigValues::int(
                $app->make(Repository::class),
                'indexnow.batch_size',
                SubmitJob::MAX_URLS_PER_REQUEST,
            );

            return new SubmitJob(
                $app->make(UrlStore::class),
                $app->make(IndexNowClient::class),
                $app->make(IndexNowConfig::class),
                min(max(1, $batchSize), SubmitJob::MAX_URLS_PER_REQUEST),
            );
        });

        $this->app->singleton(IndexNowManager::class, static fn (Container $app): IndexNowManager => new IndexNowManager(
            $app->make(UrlStore::class),
            static fn (): SubmitJob => $app->make(SubmitJob::class),
            $app->make(EventDispatcher::class),
            $app->make(BusDispatcher::class),
            ConfigValues::bool($app->make(Repository::class), 'indexnow.enabled', true),
            $app->make(QueueOptions::class),
        ));

        $this->app->alias(IndexNowManager::class, 'indexnow');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [self::configPath() => $this->app->configPath('indexnow.php')],
                ['indexnow', 'indexnow-config'],
            );

            $this->publishesMigrations(
                [self::migrationsPath() => $this->app->databasePath('migrations')],
                ['indexnow', 'indexnow-migrations'],
            );

            $this->commands([
                FlushCommand::class,
                GenerateKeyCommand::class,
                StatusCommand::class,
                SubmitCommand::class,
            ]);
        }

        $this->registerKeyRoute();
        $this->registerSchedule();
    }

    private function registerKeyRoute(): void
    {
        $config = $this->app->make(Repository::class);

        if (! ConfigValues::bool($config, 'indexnow.key_route.enabled', false)) {
            return;
        }

        $key = ConfigValues::string($config, 'indexnow.key');

        if ($key === null) {
            return;
        }

        $middleware = $config->get('indexnow.key_route.middleware');
        $middleware = \is_array($middleware) ? array_values($middleware) : [];

        $this->app->make(Router::class)
            ->middleware($middleware)
            ->get($key.'.txt', KeyFileController::class)
            ->name('indexnow.key');
    }

    private function registerSchedule(): void
    {
        // No console, no scheduler: the callback below only ever fires when
        // something resolves the Schedule, which only Artisan does.
        $config = $this->app->make(Repository::class);

        if (! ConfigValues::bool($config, 'indexnow.schedule.enabled', false)) {
            return;
        }

        $cron = ConfigValues::string($config, 'indexnow.schedule.cron') ?? self::DEFAULT_CRON;

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule) use ($cron): void {
            $schedule->command('indexnow:flush')
                ->cron($cron)
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    private static function configPath(): string
    {
        return \dirname(__DIR__).'/config/indexnow.php';
    }

    private static function migrationsPath(): string
    {
        return \dirname(__DIR__).'/database/migrations';
    }
}
