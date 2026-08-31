<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature\Console;

use Illuminate\Console\Command;
use SlimAD\IndexNow\Laravel\Tests\TestCase;

final class GenerateKeyCommandTest extends TestCase
{
    public function test_it_prints_a_key_and_its_location(): void
    {
        $this->config()->set('indexnow.host', 'shop.example.com');

        ['status' => $status, 'output' => $output] = $this->runCommand('indexnow:key');

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Add the following to your .env file:', $output);
        self::assertStringContainsString('INDEXNOW_HOST=shop.example.com', $output);
        self::assertStringContainsString('Set INDEXNOW_KEY_ROUTE_ENABLED=true', $output);

        self::assertSame(1, preg_match('/^INDEXNOW_KEY=([0-9a-f]{32})$/m', $output, $matches));

        self::assertStringContainsString(
            \sprintf('readable as text/plain at https://shop.example.com/%s.txt', $matches[1]),
            $output,
        );

        self::assertMatchesRegularExpression(
            '/\n\nINDEXNOW_HOST=/',
            $output,
            'the env block is separated from the surrounding prose',
        );
        self::assertMatchesRegularExpression('/INDEXNOW_KEY=[0-9a-f]{32}\n\n/', $output);
    }

    public function test_it_falls_back_to_a_placeholder_host(): void
    {
        $this->config()->set('indexnow.host', null);

        self::assertStringContainsString(
            'INDEXNOW_HOST=example.com',
            $this->runCommand('indexnow:key')['output'],
        );
    }

    public function test_a_blank_host_also_falls_back_to_the_placeholder(): void
    {
        $this->config()->set('indexnow.host', '');

        self::assertStringContainsString(
            'INDEXNOW_HOST=example.com',
            $this->runCommand('indexnow:key')['output'],
        );
    }

    public function test_it_honours_the_requested_length(): void
    {
        foreach ([8, 33, 128] as $length) {
            $output = $this->runCommand('indexnow:key', ['--length' => $length])['output'];

            self::assertSame(
                1,
                preg_match('/^INDEXNOW_KEY=([0-9a-f]+)$/m', $output, $matches),
                'the key has to be printed on its own line',
            );
            self::assertSame($length, \strlen($matches[1]));
        }
    }

    public function test_it_rejects_a_length_outside_the_protocol_limits(): void
    {
        $this->command('indexnow:key', ['--length' => 7])
            ->expectsOutputToContain('Key length must be between 8 and 128 characters, 7 given.')
            ->assertExitCode(2);

        $this->command('indexnow:key', ['--length' => 129])
            ->expectsOutputToContain('129 given')
            ->assertExitCode(2);
    }
}
