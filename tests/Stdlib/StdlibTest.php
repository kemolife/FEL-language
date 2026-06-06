<?php
declare(strict_types=1);
namespace Fel\Tests\Stdlib;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class StdlibTest extends TestCase
{
    private Engine $engine;

    protected function setUp(): void
    {
        $this->engine = new Engine();
    }

    private function eval(string $src): mixed
    {
        $result = $this->engine->eval($src);
        self::assertFalse($this->engine->hasErrors(), implode(', ', $this->engine->errors()));
        return $result;
    }

    // ── math module ───────────────────────────────────────────────────────

    public function test_math_sqrt(): void
    {
        self::assertSame(4.0, $this->eval('import "stdlib/math"; math.sqrt(16.0)'));
    }

    public function test_math_pow(): void
    {
        self::assertSame(1024, $this->eval('import "stdlib/math"; math.pow(2, 10)'));
    }

    public function test_math_abs(): void
    {
        self::assertSame(5, $this->eval('import "stdlib/math"; math.abs(-5)'));
    }

    public function test_math_floor_ceil_round(): void
    {
        self::assertSame(3, $this->eval('import "stdlib/math"; math.floor(3.9)'));
        self::assertSame(4, $this->eval('import "stdlib/math"; math.ceil(3.1)'));
        self::assertSame(4, $this->eval('import "stdlib/math"; math.round(3.5)'));
    }

    public function test_math_min_max(): void
    {
        self::assertSame(2, $this->eval('import "stdlib/math"; math.min(2, 5)'));
        self::assertSame(5, $this->eval('import "stdlib/math"; math.max(2, 5)'));
    }

    public function test_math_pi_constant(): void
    {
        $pi = $this->eval('import "stdlib/math"; math.pi');
        self::assertEqualsWithDelta(3.14159, $pi, 0.0001);
    }

    // ── string module ─────────────────────────────────────────────────────

    public function test_string_split(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->eval('import "stdlib/string"; string.split("a,b,c", ",")'));
    }

    public function test_string_replace(): void
    {
        self::assertSame('hello FEL', $this->eval('import "stdlib/string"; string.replace("hello world", "world", "FEL")'));
    }

    public function test_string_starts_ends_with(): void
    {
        self::assertTrue($this->eval('import "stdlib/string"; string.starts_with("hello", "hel")'));
        self::assertFalse($this->eval('import "stdlib/string"; string.starts_with("hello", "ell")'));
        self::assertTrue($this->eval('import "stdlib/string"; string.ends_with("hello", "llo")'));
        self::assertFalse($this->eval('import "stdlib/string"; string.ends_with("hello", "hel")'));
    }

    public function test_string_index_of(): void
    {
        self::assertSame(6, $this->eval('import "stdlib/string"; string.index_of("hello world", "world")'));
        self::assertSame(-1, $this->eval('import "stdlib/string"; string.index_of("hello", "xyz")'));
    }

    public function test_string_substr(): void
    {
        self::assertSame('ello', $this->eval('import "stdlib/string"; string.substr("hello", 1, 4)'));
    }

    public function test_string_repeat(): void
    {
        self::assertSame('aaa', $this->eval('import "stdlib/string"; string.repeat("a", 3)'));
    }

    public function test_string_pad_left_right(): void
    {
        self::assertSame('  hi', $this->eval('import "stdlib/string"; string.pad_left("hi", 4, " ")'));
        self::assertSame('hi  ', $this->eval('import "stdlib/string"; string.pad_right("hi", 4, " ")'));
    }

    // ── array module ──────────────────────────────────────────────────────

    public function test_array_map(): void
    {
        self::assertSame([2, 4, 6], $this->eval('import "stdlib/array"; array.map([1,2,3], fn(x) { x * 2 })'));
    }

    public function test_array_filter(): void
    {
        self::assertSame([2, 4], $this->eval('import "stdlib/array"; array.filter([1,2,3,4], fn(x) { x % 2 == 0 })'));
    }

    public function test_array_reduce(): void
    {
        self::assertSame(10, $this->eval('import "stdlib/array"; array.reduce([1,2,3,4], fn(acc, x) { acc + x }, 0)'));
    }

    public function test_array_sort(): void
    {
        self::assertSame([1, 2, 3], $this->eval('import "stdlib/array"; array.sort([3,1,2])'));
    }

    public function test_array_reverse(): void
    {
        self::assertSame([3, 2, 1], $this->eval('import "stdlib/array"; array.reverse([1,2,3])'));
    }

    public function test_array_flatten(): void
    {
        self::assertSame([1, 2, 3, 4], $this->eval('import "stdlib/array"; array.flatten([[1,2],[3,4]])'));
    }

    public function test_array_unique(): void
    {
        self::assertSame([1, 2, 3], $this->eval('import "stdlib/array"; array.unique([1,1,2,3,2])'));
    }

    public function test_array_find(): void
    {
        self::assertSame(4, $this->eval('import "stdlib/array"; array.find([1,2,3,4,5], fn(x) { x > 3 })'));
    }

    public function test_array_every_some(): void
    {
        self::assertTrue($this->eval('import "stdlib/array"; array.every([2,4,6], fn(x) { x % 2 == 0 })'));
        self::assertFalse($this->eval('import "stdlib/array"; array.every([1,2,3], fn(x) { x % 2 == 0 })'));
        self::assertTrue($this->eval('import "stdlib/array"; array.some([1,2,3], fn(x) { x % 2 == 0 })'));
        self::assertFalse($this->eval('import "stdlib/array"; array.some([1,3,5], fn(x) { x % 2 == 0 })'));
    }

    // ── json module ───────────────────────────────────────────────────────

    public function test_json_decode_object(): void
    {
        $result = $this->eval('import "stdlib/json"; json.decode("{\"name\":\"FEL\",\"version\":1}")');
        self::assertSame(['name' => 'FEL', 'version' => 1], $result);
    }

    public function test_json_decode_array(): void
    {
        $result = $this->eval('import "stdlib/json"; json.decode("[1,2,3]")');
        self::assertSame([1, 2, 3], $result);
    }

    public function test_json_encode(): void
    {
        $result = $this->eval('import "stdlib/json"; json.encode({"name": "FEL"})');
        self::assertSame('{"name":"FEL"}', $result);
    }

    public function test_json_roundtrip(): void
    {
        $result = $this->eval('
            import "stdlib/json";
            let data = json.decode("{\"x\":42}");
            data["x"]
        ');
        self::assertSame(42, $result);
    }

    // ── types module ──────────────────────────────────────────────────────

    public function test_types_is_int(): void
    {
        self::assertTrue($this->eval('import "stdlib/types"; types.is_int(42)'));
        self::assertFalse($this->eval('import "stdlib/types"; types.is_int("hi")'));
    }

    public function test_types_is_string(): void
    {
        self::assertTrue($this->eval('import "stdlib/types"; types.is_string("hi")'));
        self::assertFalse($this->eval('import "stdlib/types"; types.is_string(42)'));
    }

    public function test_types_is_array(): void
    {
        self::assertTrue($this->eval('import "stdlib/types"; types.is_array([1,2])'));
        self::assertFalse($this->eval('import "stdlib/types"; types.is_array(42)'));
    }

    public function test_types_is_null(): void
    {
        self::assertTrue($this->eval('import "stdlib/types"; types.is_null(null)'));
        self::assertFalse($this->eval('import "stdlib/types"; types.is_null(0)'));
    }

    public function test_types_is_bool(): void
    {
        self::assertTrue($this->eval('import "stdlib/types"; types.is_bool(true)'));
        self::assertFalse($this->eval('import "stdlib/types"; types.is_bool(1)'));
    }
}
