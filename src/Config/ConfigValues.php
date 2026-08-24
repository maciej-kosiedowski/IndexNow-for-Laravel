<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Config;

use Illuminate\Contracts\Config\Repository;

/**
 * Reads configuration the way `env()` leaves it: values may be missing, blank or
 * a string where a number is expected.
 *
 * @internal
 */
final class ConfigValues
{
    /**
     * A non-blank string, or null when the value is missing, blank or not a string.
     */
    public static function string(Repository $config, string $key): ?string
    {
        return self::toString($config->get($key));
    }

    public static function toString(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function int(Repository $config, string $key, int $default): int
    {
        return self::toInt($config->get($key), $default);
    }

    public static function toInt(mixed $value, int $default): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);

            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }
        }

        return $default;
    }

    /**
     * Like {@see self::toInt()}, but treats zero and negative numbers as "not set".
     */
    public static function toPositiveInt(mixed $value, int $default): int
    {
        $number = self::toInt($value, $default);

        return $number > 0 ? $number : $default;
    }

    public static function bool(Repository $config, string $key, bool $default): bool
    {
        $value = $config->get($key);

        return \is_bool($value) ? $value : $default;
    }

    /**
     * @param  list<int>  $default
     * @return list<int>
     */
    public static function intList(Repository $config, string $key, array $default): array
    {
        $value = $config->get($key);

        if (! \is_array($value)) {
            return $default;
        }

        $numbers = [];

        foreach ($value as $entry) {
            if (\is_int($entry) && $entry >= 0) {
                $numbers[] = $entry;
            }
        }

        return $numbers === [] ? $default : $numbers;
    }
}
