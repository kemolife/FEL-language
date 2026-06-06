<?php
declare(strict_types=1);
namespace Fel\Tests;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
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

    // ── Literals ──────────────────────────────────────────────────────────

    public function test_integer_literal(): void
    {
        self::assertSame(42, $this->eval('42'));
    }

    public function test_negative_integer(): void
    {
        self::assertSame(-7, $this->eval('-7'));
    }

    public function test_float_literal(): void
    {
        self::assertSame(3.14, $this->eval('3.14'));
    }

    public function test_string_literal(): void
    {
        self::assertSame('hello', $this->eval('"hello"'));
    }

    public function test_true_literal(): void
    {
        self::assertTrue($this->eval('true'));
    }

    public function test_false_literal(): void
    {
        self::assertFalse($this->eval('false'));
    }

    public function test_null_literal(): void
    {
        self::assertNull($this->eval('null'));
    }

    // ── Arithmetic ────────────────────────────────────────────────────────

    public function test_addition(): void
    {
        self::assertSame(7, $this->eval('3 + 4'));
    }

    public function test_subtraction(): void
    {
        self::assertSame(1, $this->eval('3 - 2'));
    }

    public function test_multiplication(): void
    {
        self::assertSame(12, $this->eval('3 * 4'));
    }

    public function test_integer_division_truncates(): void
    {
        self::assertSame(2, $this->eval('5 / 2'));
        self::assertSame(2, $this->eval('4 / 2'));
    }

    public function test_float_division(): void
    {
        self::assertSame(2.5, $this->eval('5.0 / 2'));
    }

    public function test_modulo(): void
    {
        self::assertSame(1, $this->eval('7 % 3'));
    }

    public function test_operator_precedence(): void
    {
        self::assertSame(14, $this->eval('2 + 3 * 4'));
    }

    public function test_parentheses_override_precedence(): void
    {
        self::assertSame(20, $this->eval('(2 + 3) * 4'));
    }

    // ── Comparison ────────────────────────────────────────────────────────

    public function test_equal(): void
    {
        self::assertTrue($this->eval('1 == 1'));
        self::assertFalse($this->eval('1 == 2'));
    }

    public function test_not_equal(): void
    {
        self::assertTrue($this->eval('1 != 2'));
        self::assertFalse($this->eval('1 != 1'));
    }

    public function test_less_than(): void
    {
        self::assertTrue($this->eval('1 < 2'));
        self::assertFalse($this->eval('2 < 1'));
    }

    public function test_greater_than(): void
    {
        self::assertTrue($this->eval('2 > 1'));
        self::assertFalse($this->eval('1 > 2'));
    }

    public function test_less_than_or_equal(): void
    {
        self::assertTrue($this->eval('2 <= 2'));
        self::assertTrue($this->eval('1 <= 2'));
        self::assertFalse($this->eval('3 <= 2'));
    }

    public function test_greater_than_or_equal(): void
    {
        self::assertTrue($this->eval('2 >= 2'));
        self::assertTrue($this->eval('3 >= 2'));
        self::assertFalse($this->eval('1 >= 2'));
    }

    // ── Boolean logic ─────────────────────────────────────────────────────

    public function test_and_true(): void
    {
        self::assertTrue($this->eval('true && true'));
    }

    public function test_and_false(): void
    {
        self::assertFalse($this->eval('true && false'));
    }

    public function test_or_false(): void
    {
        self::assertFalse($this->eval('false || false'));
    }

    public function test_or_true(): void
    {
        self::assertTrue($this->eval('false || true'));
    }

    public function test_not(): void
    {
        self::assertFalse($this->eval('!true'));
        self::assertTrue($this->eval('!false'));
    }

    // ── Variables ─────────────────────────────────────────────────────────

    public function test_let_and_read(): void
    {
        self::assertSame(10, $this->eval('let x = 10; x'));
    }

    public function test_reassignment(): void
    {
        self::assertSame(20, $this->eval('let x = 10; x = 20; x'));
    }

    public function test_multiple_variables(): void
    {
        self::assertSame(30, $this->eval('let a = 10; let b = 20; a + b'));
    }

    // ── Control flow ──────────────────────────────────────────────────────

    public function test_if_true_branch(): void
    {
        self::assertSame('yes', $this->eval('if (true) { "yes" } else { "no" }'));
    }

    public function test_if_false_branch(): void
    {
        self::assertSame('no', $this->eval('if (false) { "yes" } else { "no" }'));
    }

    public function test_if_without_else_returns_null(): void
    {
        self::assertNull($this->eval('if (false) { "yes" }'));
    }

    public function test_while_loop(): void
    {
        self::assertSame(5, $this->eval('let i = 0; while (i < 5) { i = i + 1; } i'));
    }

    public function test_for_loop(): void
    {
        self::assertSame(6, $this->eval('let s = 0; for (x in [1,2,3]) { s = s + x; } s'));
    }

    // ── Functions ─────────────────────────────────────────────────────────

    public function test_function_call(): void
    {
        self::assertSame(7, $this->eval('let add = fn(a, b) { a + b }; add(3, 4)'));
    }

    public function test_function_closure(): void
    {
        self::assertSame(15, $this->eval('
            let base = 10;
            let add = fn(x) { base + x };
            add(5)
        '));
    }

    public function test_recursive_function(): void
    {
        self::assertSame(120, $this->eval('
            let fact = fn(n) { if (n <= 1) { 1 } else { n * fact(n - 1) } };
            fact(5)
        '));
    }

    public function test_higher_order_function(): void
    {
        self::assertSame(10, $this->eval('
            let apply = fn(f, x) { f(x) };
            let double = fn(x) { x * 2 };
            apply(double, 5)
        '));
    }

    // ── Arrays ────────────────────────────────────────────────────────────

    public function test_array_literal(): void
    {
        self::assertSame([1, 2, 3], $this->eval('[1, 2, 3]'));
    }

    public function test_array_index(): void
    {
        self::assertSame(2, $this->eval('[1, 2, 3][1]'));
    }

    public function test_empty_array(): void
    {
        self::assertSame([], $this->eval('[]'));
    }

    public function test_nested_array(): void
    {
        self::assertSame(4, $this->eval('[[1, 2], [3, 4]][1][1]'));
    }

    // ── Hashes ────────────────────────────────────────────────────────────

    public function test_hash_literal(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], $this->eval('{"a": 1, "b": 2}'));
    }

    public function test_hash_bracket_access(): void
    {
        self::assertSame('Alice', $this->eval('{"name": "Alice"}["name"]'));
    }

    public function test_hash_dot_access(): void
    {
        self::assertSame(30, $this->eval('let u = {"age": 30}; u.age'));
    }

    // ── String operations ─────────────────────────────────────────────────

    public function test_string_concatenation(): void
    {
        self::assertSame('helloworld', $this->eval('"hello" + "world"'));
    }

    // ── Builtins ──────────────────────────────────────────────────────────

    public function test_len_string(): void
    {
        self::assertSame(5, $this->eval('len("hello")'));
    }

    public function test_len_array(): void
    {
        self::assertSame(3, $this->eval('len([1, 2, 3])'));
    }

    public function test_type(): void
    {
        self::assertSame('INTEGER', $this->eval('type(42)'));
        self::assertSame('STRING', $this->eval('type("hi")'));
        self::assertSame('BOOLEAN', $this->eval('type(true)'));
        self::assertSame('ARRAY', $this->eval('type([])'));
    }

    public function test_push(): void
    {
        self::assertSame([1, 2, 3], $this->eval('push([1, 2], 3)'));
    }

    public function test_first_last_rest(): void
    {
        self::assertSame(1, $this->eval('first([1, 2, 3])'));
        self::assertSame(3, $this->eval('last([1, 2, 3])'));
        self::assertSame([2, 3], $this->eval('rest([1, 2, 3])'));
    }

    public function test_range(): void
    {
        self::assertSame([0, 1, 2, 3, 4], $this->eval('range(5)'));
        self::assertSame([2, 3, 4], $this->eval('range(2, 5)'));
        self::assertSame([0, 2, 4], $this->eval('range(0, 6, 2)'));
    }

    public function test_split_join(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->eval('split("a,b,c", ",")'));
        self::assertSame('a-b-c', $this->eval('join(["a","b","c"], "-")'));
    }

    public function test_string_case(): void
    {
        self::assertSame('HELLO', $this->eval('upper("hello")'));
        self::assertSame('hello', $this->eval('lower("HELLO")'));
    }

    public function test_trim(): void
    {
        self::assertSame('hi', $this->eval('trim("  hi  ")'));
    }

    public function test_contains(): void
    {
        self::assertTrue($this->eval('contains("hello world", "world")'));
        self::assertFalse($this->eval('contains("hello", "xyz")'));
    }

    public function test_to_int_to_str(): void
    {
        self::assertSame(42, $this->eval('to_int("42")'));
        self::assertSame('42', $this->eval('to_str(42)'));
    }

    public function test_map_filter_reduce(): void
    {
        self::assertSame([2, 4, 6], $this->eval('map([1,2,3], fn(x) { x * 2 })'));
        self::assertSame([2, 4], $this->eval('filter([1,2,3,4], fn(x) { x % 2 == 0 })'));
        self::assertSame(10, $this->eval('reduce([1,2,3,4], fn(acc,x) { acc + x }, 0)'));
    }

    public function test_sort_reverse(): void
    {
        self::assertSame([1, 2, 3], $this->eval('sort([3, 1, 2])'));
        self::assertSame([3, 2, 1], $this->eval('reverse([1, 2, 3])'));
    }

    public function test_flatten_unique(): void
    {
        self::assertSame([1, 2, 3, 4], $this->eval('flatten([[1,2],[3,4]])'));
        self::assertSame([1, 2, 3], $this->eval('unique([1,1,2,3,2])'));
    }

    public function test_keys_values(): void
    {
        self::assertSame(['a'], $this->eval('keys({"a": 1})'));
        self::assertSame([1], $this->eval('values({"a": 1})'));
    }

    public function test_math_builtins(): void
    {
        self::assertSame(4.0, $this->eval('sqrt(16.0)'));
        self::assertSame(8, $this->eval('pow(2, 3)'));
        self::assertSame(5, $this->eval('abs(-5)'));
        self::assertSame(3, $this->eval('floor(3.9)'));
        self::assertSame(4, $this->eval('ceil(3.1)'));
        self::assertSame(4, $this->eval('round(3.5)'));
        self::assertSame(3, $this->eval('min(3, 5)'));
        self::assertSame(5, $this->eval('max(3, 5)'));
    }

    // ── Engine PHP API ────────────────────────────────────────────────────

    public function test_setVar_exposes_variable_to_script(): void
    {
        $this->engine->setVar('x', 42);
        self::assertSame(84, $this->engine->eval('x * 2'));
    }

    public function test_setVar_array(): void
    {
        $this->engine->setVar('items', [1, 2, 3]);
        self::assertSame(3, $this->engine->eval('len(items)'));
    }

    public function test_setVar_hash(): void
    {
        $this->engine->setVar('user', ['name' => 'Alice', 'age' => 30]);
        self::assertSame('Alice', $this->engine->eval('user["name"]'));
    }

    public function test_getVar_reads_script_variable(): void
    {
        $this->engine->eval('let result = 99;');
        self::assertSame(99, $this->engine->getVar('result'));
    }

    public function test_registerFunc_callable_from_script(): void
    {
        $this->engine->registerFunc('double', fn($x) => $x * 2);
        self::assertSame(20, $this->engine->eval('double(10)'));
    }

    public function test_registerFunc_multi_arg(): void
    {
        $this->engine->registerFunc('add', fn($a, $b) => $a + $b);
        self::assertSame(7, $this->engine->eval('add(3, 4)'));
    }

    public function test_errors_returned_on_parse_error(): void
    {
        $this->engine->eval('let = ;');
        self::assertTrue($this->engine->hasErrors());
        self::assertNotEmpty($this->engine->errors());
    }

    public function test_errors_cleared_between_evals(): void
    {
        $this->engine->eval('let = ;');
        self::assertTrue($this->engine->hasErrors());

        $this->engine->eval('42');
        self::assertFalse($this->engine->hasErrors());
    }

    public function test_state_persists_across_evals(): void
    {
        $this->engine->eval('let x = 10;');
        $result = $this->engine->eval('x + 5');
        self::assertSame(15, $result);
    }

    // ── Sandbox mode ──────────────────────────────────────────────────────

    public function test_sandbox_blocks_io_builtins(): void
    {
        $sandboxed = new Engine(sandbox: true);
        $result = $sandboxed->eval('read_file("any.txt")');
        self::assertTrue($sandboxed->hasErrors());
        self::assertNull($result);
    }

    public function test_sandbox_allows_math(): void
    {
        $sandboxed = new Engine(sandbox: true);
        self::assertSame(4, $sandboxed->eval('2 + 2'));
    }
}
