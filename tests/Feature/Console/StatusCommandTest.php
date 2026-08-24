<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature\Console;

use Illuminate\Console\Command;
use SlimAD\IndexNow\Config\IndexNowConfig;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class StatusCommandTest extends TestCase
{
    public function test_it_shows_the_configuration(): void
    {
        $this->container()->make(IndexNowManager::class)->submit('https://example.com/a');

        ['status' => $status, 'output' => $output] = $this->runCommand('indexnow:status');

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Setting', $output);
        self::assertStringContainsString('| Enabled', $output);
        self::assertStringContainsString('yes', $output);
        self::assertStringContainsString('| Store', $output);
        self::assertStringContainsString('array', $output);
        self::assertStringContainsString('| Pending URLs', $output);
        self::assertStringContainsString('| Host', $output);
        self::assertStringContainsString('example.com', $output);
        self::assertStringContainsString('no (submits inline)', $output);
        self::assertStringContainsString('Engine: indexnow', $output);
        self::assertStringContainsString('https://api.indexnow.org/indexnow', $output);
        self::assertStringContainsString('Key location: indexnow', $output);
        self::assertStringContainsString('https://example.com/abcdef0123456789abcdef0123456789.txt', $output);
    }

    public function test_it_reports_that_queueing_is_on(): void
    {
        $this->config()->set('indexnow.queue.enabled', true);
        $this->container()->forgetInstance(IndexNowManager::class);

        self::assertStringContainsString('| Queue', $this->runCommand('indexnow:status')['output']);
    }

    public function test_it_reports_a_disabled_package(): void
    {
        $this->config()->set('indexnow.enabled', false);
        $this->container()->forgetInstance(IndexNowManager::class);

        self::assertStringContainsString('no', $this->runCommand('indexnow:status')['output']);
    }

    public function test_it_fails_with_an_actionable_message_when_the_host_is_missing(): void
    {
        $this->config()->set('indexnow.host', null);
        $this->container()->forgetInstance(IndexNowConfig::class);

        ['status' => $status, 'output' => $output] = $this->runCommand('indexnow:status');

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('INDEXNOW_HOST', $output);
        self::assertStringContainsString('Setting', $output, 'the basics are still reported');
        self::assertStringContainsString('| Pending URLs', $output, 'the basics are still reported');
    }

    public function test_it_reports_a_store_that_is_not_configured(): void
    {
        $this->config()->set('indexnow.store', null);
        $this->config()->set('indexnow.stores.cache.driver', 'cache');

        self::assertStringContainsString('<not set>', $this->runCommand('indexnow:status')['output']);
    }
}
