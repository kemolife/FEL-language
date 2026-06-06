<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_yield_sequence_to_array(): void
    {
        self::assertSame([1, 2, 3], $this->eval(
            'let nums = fn() { yield 1; yield 2; yield 3; }; to_array(nums())'
        ));
    }

    public function test_generator_in_for_in(): void
    {
        self::assertSame(30, $this->eval(
            'let g = fn() { yield 10; yield 20; }; let s = 0; for (x in g()) { s = s + x; } s'
        ));
    }

    public function test_generator_with_loop_logic(): void
    {
        self::assertSame([0, 1, 2, 3], $this->eval(
            'let count = fn(n) { let i = 0; while (i < n) { yield i; i = i + 1; } }; to_array(count(4))'
        ));
    }

    public function test_take_from_infinite_generator(): void
    {
        self::assertSame([0, 1, 2], $this->eval(
            'let nat = fn() { let i = 0; while (true) { yield i; i = i + 1; } }; to_array(take(nat(), 3))'
        ));
    }

    public function test_non_generator_function_unaffected(): void
    {
        self::assertSame(7, $this->eval('let add = fn(a, b) { a + b }; add(3, 4)'));
    }
}
