<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use SlimAD\IndexNow\Laravel\Config\IndexNowConfigFactory;
use SlimAD\IndexNow\ValueObject\Key;

final class GenerateKeyCommand extends Command
{
    private const MIN_LENGTH = 8;

    private const MAX_LENGTH = 128;

    /** @var string */
    protected $signature = 'indexnow:key
                            {--length=32 : Key length, between 8 and 128 characters}';

    /** @var string */
    protected $description = 'Generate an IndexNow key and show the configuration it belongs in';

    public function handle(Repository $config): int
    {
        $length = (int) $this->option('length');

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            $this->components->error(\sprintf(
                'Key length must be between %d and %d characters, %d given.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
                $length,
            ));

            return self::INVALID;
        }

        $key = new Key(substr(bin2hex(random_bytes($length)), 0, $length));

        $configuredHost = $config->get('indexnow.host');
        $host = \is_string($configuredHost) && $configuredHost !== '' ? $configuredHost : 'example.com';

        $this->components->info('Add the following to your .env file:');
        $this->newLine();
        $this->line(\sprintf('INDEXNOW_HOST=%s', $host));
        $this->line(\sprintf('INDEXNOW_KEY=%s', $key->value));
        $this->newLine();
        $this->components->info(\sprintf(
            'The key has to be readable as text/plain at %s.',
            IndexNowConfigFactory::defaultKeyLocation($host, $key->value),
        ));
        $this->components->info('Set INDEXNOW_KEY_ROUTE_ENABLED=true to let this package serve it for you.');

        return self::SUCCESS;
    }
}
