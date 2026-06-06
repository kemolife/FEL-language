<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class StructTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_struct_literal_field_access(): void
    {
        self::assertSame(3, $this->eval('struct Point { x, y } let p = Point{x: 3, y: 4}; p.x'));
    }

    public function test_struct_second_field(): void
    {
        self::assertSame(4, $this->eval('struct Point { x, y } let p = Point{x: 3, y: 4}; p.y'));
    }

    public function test_method_no_args(): void
    {
        self::assertSame(5.0, $this->eval('
            struct Point { x, y }
            fn (p Point) dist() { sqrt(p.x * p.x + p.y * p.y) }
            let p = Point{x: 3, y: 4};
            p.dist()
        '));
    }

    public function test_method_with_args_returns_struct(): void
    {
        self::assertSame(6, $this->eval('
            struct Point { x, y }
            fn (p Point) scale(k) { Point{x: p.x * k, y: p.y * k} }
            let p = Point{x: 3, y: 4};
            p.scale(2).x
        '));
    }

    public function test_method_chaining(): void
    {
        self::assertSame(8, $this->eval('
            struct Point { x, y }
            fn (p Point) scale(k) { Point{x: p.x * k, y: p.y * k} }
            let p = Point{x: 1, y: 2};
            p.scale(2).scale(2).y
        '));
    }

    public function test_unknown_field_is_error(): void
    {
        $engine = new Engine();
        $engine->eval('struct Point { x, y } Point{z: 1}');
        self::assertTrue($engine->hasErrors());
    }

    public function test_interface_implemented_true(): void
    {
        self::assertTrue($this->eval('
            interface Shape { area }
            struct Circle { r }
            fn (c Circle) area() { c.r * c.r * 3 }
            implements(Circle{r: 2}, Shape)
        '));
    }

    public function test_interface_not_implemented_false(): void
    {
        self::assertFalse($this->eval('
            interface Shape { area }
            struct Point { x, y }
            implements(Point{x: 1, y: 2}, Shape)
        '));
    }
}
