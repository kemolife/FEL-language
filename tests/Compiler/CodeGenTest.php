<?php
declare(strict_types=1);
namespace Fel\Tests\Compiler;

use Fel\Compiler\Compiler;
use PHPUnit\Framework\TestCase;

final class CodeGenTest extends TestCase
{
    private Compiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new Compiler();
    }

    private function compile(string $src): string
    {
        return $this->compiler->compileFile($src);
    }

    private function assertContainsIR(string $needle, string $ir, string $message = ''): void
    {
        self::assertStringContainsString($needle, $ir, $message);
    }

    // ── Module structure ──────────────────────────────────────────────────

    public function test_emits_module_header(): void
    {
        $ir = $this->compile('42');
        self::assertStringContainsString('target datalayout', $ir);
        self::assertStringContainsString('%FelVal', $ir);
    }

    public function test_emits_fel_main_function(): void
    {
        $ir = $this->compile('42');
        self::assertStringContainsString('define internal i32 @fel_main()', $ir);
    }

    public function test_emits_main_wrapper(): void
    {
        $ir = $this->compile('42');
        self::assertStringContainsString('define i32 @main()', $ir);
        self::assertStringContainsString('@fel_gc_init', $ir);
        self::assertStringContainsString('@fel_gc_shutdown', $ir);
    }

    public function test_emits_gc_init_and_shutdown(): void
    {
        $ir = $this->compile('display(1)');
        self::assertStringContainsString('fel_gc_init', $ir);
        self::assertStringContainsString('fel_gc_shutdown', $ir);
    }

    // ── Integer literals ──────────────────────────────────────────────────

    public function test_integer_literal_boxes_value(): void
    {
        $ir = $this->compile('42');
        self::assertStringContainsString('fel_val_int', $ir);
        self::assertStringContainsString('42', $ir);
    }

    public function test_negative_integer_prefix(): void
    {
        $ir = $this->compile('-7');
        self::assertStringContainsString('fel_val_int', $ir);
    }

    // ── Float literals ────────────────────────────────────────────────────

    public function test_float_literal_boxes_value(): void
    {
        $ir = $this->compile('3.14');
        self::assertStringContainsString('fel_val_float', $ir);
    }

    // ── String literals ───────────────────────────────────────────────────

    public function test_string_literal_emits_str_new(): void
    {
        $ir = $this->compile('"hello"');
        self::assertStringContainsString('fel_str_new', $ir);
        self::assertStringContainsString('hello', $ir);
    }

    // ── Boolean literals ──────────────────────────────────────────────────

    public function test_true_literal(): void
    {
        $ir = $this->compile('true');
        self::assertStringContainsString('fel_val_bool', $ir);
    }

    public function test_false_literal(): void
    {
        $ir = $this->compile('false');
        self::assertStringContainsString('fel_val_bool', $ir);
    }

    // ── Null literal ──────────────────────────────────────────────────────

    public function test_null_literal_emits_val_null(): void
    {
        $ir = $this->compile('null');
        self::assertStringContainsString('fel_val_null', $ir);
    }

    // ── Arithmetic ────────────────────────────────────────────────────────

    public function test_integer_addition_emits_binary_op(): void
    {
        $ir = $this->compile('3 + 4');
        self::assertStringContainsString('fel_binop', $ir);
    }

    public function test_subtraction_emits_binary_op(): void
    {
        $ir = $this->compile('10 - 3');
        self::assertStringContainsString('fel_binop', $ir);
    }

    public function test_multiplication_emits_binary_op(): void
    {
        $ir = $this->compile('3 * 4');
        self::assertStringContainsString('fel_binop', $ir);
    }

    // ── Variables ─────────────────────────────────────────────────────────

    public function test_let_statement_emits_store(): void
    {
        $ir = $this->compile('let x = 42;');
        self::assertStringContainsString('store', $ir);
    }

    public function test_variable_load(): void
    {
        $ir = $this->compile('let x = 42; x');
        self::assertStringContainsString('load', $ir);
    }

    // ── display() call ────────────────────────────────────────────────────

    public function test_display_integer_emits_fel_display(): void
    {
        $ir = $this->compile('display(42)');
        self::assertStringContainsString('fel_display', $ir);
    }

    public function test_display_string_emits_fel_display(): void
    {
        $ir = $this->compile('display("hi")');
        self::assertStringContainsString('fel_display', $ir);
    }

    // ── Arrays ────────────────────────────────────────────────────────────

    public function test_array_literal_emits_arr_new(): void
    {
        $ir = $this->compile('[1, 2, 3]');
        self::assertStringContainsString('fel_arr_new', $ir);
        self::assertStringContainsString('fel_arr_push', $ir);
    }

    // ── Parse errors ─────────────────────────────────────────────────────

    public function test_parse_error_throws_runtime_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Parse errors/');
        $this->compile('let = ;');
    }

    // ── Output is valid text ──────────────────────────────────────────────

    public function test_output_starts_with_target_triple(): void
    {
        $ir = $this->compile('1');
        self::assertStringStartsWith('; Module', $ir);
    }

    public function test_output_contains_declare_sections(): void
    {
        $ir = $this->compile('display(1)');
        self::assertStringContainsString('declare', $ir);
    }
}
