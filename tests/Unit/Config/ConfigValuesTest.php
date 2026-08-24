<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Unit\Config;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlimAD\IndexNow\Laravel\Config\ConfigValues;

final class ConfigValuesTest extends TestCase
{
    private function repository(mixed $value): Repository
    {
        return new Repository(['x' => $value]);
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function stringProvider(): iterable
    {
        yield 'plain string' => ['example.com', 'example.com'];
        yield 'padded string' => ["  example.com \n", 'example.com'];
        yield 'blank string' => ['   ', null];
        yield 'empty string' => ['', null];
        yield 'missing' => [null, null];
        yield 'integer' => [42, null];
        yield 'boolean' => [true, null];
        yield 'array' => [['example.com'], null];
    }

    #[DataProvider('stringProvider')]
    public function test_string(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, ConfigValues::string($this->repository($value), 'x'));
    }

    public function test_string_returns_null_for_an_unknown_key(): void
    {
        self::assertNull(ConfigValues::string(new Repository([]), 'missing'));
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function intProvider(): iterable
    {
        yield 'integer' => [30, 30];
        yield 'zero' => [0, 0];
        yield 'negative integer' => [-1, -1];
        yield 'numeric string' => ['30', 30];
        yield 'padded numeric string' => [' 30 ', 30];
        yield 'non numeric string' => ['thirty', 7];
        yield 'float' => [30.5, 7];
        yield 'missing' => [null, 7];
        yield 'boolean' => [true, 7];
    }

    #[DataProvider('intProvider')]
    public function test_int(mixed $value, int $expected): void
    {
        self::assertSame($expected, ConfigValues::int($this->repository($value), 'x', 7));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function boolProvider(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'string true' => ['true', false];
        yield 'integer one' => [1, false];
        yield 'missing' => [null, false];
    }

    #[DataProvider('boolProvider')]
    public function test_bool(mixed $value, bool $expected): void
    {
        self::assertSame($expected, ConfigValues::bool($this->repository($value), 'x', false));
    }

    public function test_bool_uses_the_given_default(): void
    {
        self::assertTrue(ConfigValues::bool($this->repository(null), 'x', true));
        self::assertFalse(ConfigValues::bool($this->repository(false), 'x', true));
    }

    /**
     * @return iterable<string, array{mixed, list<int>}>
     */
    public static function intListProvider(): iterable
    {
        yield 'list of integers' => [[5, 10, 15], [5, 10, 15]];
        yield 'zero is kept' => [[0, 5], [0, 5]];
        yield 'negative entries are dropped' => [[-5, 10], [10]];
        yield 'non integers are dropped' => [[5, '10', null, 15], [5, 15]];
        yield 'keys are discarded' => [['a' => 5, 'b' => 10], [5, 10]];
        yield 'empty array' => [[], [1, 2]];
        yield 'nothing usable' => [['a', 'b'], [1, 2]];
        yield 'not an array' => ['5,10', [1, 2]];
        yield 'missing' => [null, [1, 2]];
    }

    /**
     * @param  list<int>  $expected
     */
    #[DataProvider('intListProvider')]
    public function test_int_list(mixed $value, array $expected): void
    {
        self::assertSame($expected, ConfigValues::intList($this->repository($value), 'x', [1, 2]));
    }
}
