<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class TypeAnnotationTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_typed_let(): void
    {
        self::assertSame(5, $this->eval('let x: Int = 5; x'));
    }

    public function test_typed_function_params(): void
    {
        self::assertSame(7, $this->eval('let add = fn(a: Int, b: Int) { a + b }; add(3, 4)'));
    }

    public function test_typed_function_return(): void
    {
        self::assertSame(9, $this->eval('let sq = fn(n: Int): Int { n * n }; sq(3)'));
    }

    public function test_typed_and_untyped_mixed(): void
    {
        self::assertSame(10, $this->eval('let mul = fn(a: Int, b) { a * b }; mul(2, 5)'));
    }

    public function test_struct_typed_fields_still_parse(): void
    {
        self::assertSame(3, $this->eval('struct Point { x: Int, y: Int } let p = Point{x: 3, y: 4}; p.x'));
    }
}
