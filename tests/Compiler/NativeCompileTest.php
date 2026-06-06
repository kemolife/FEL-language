<?php
declare(strict_types=1);
namespace Fel\Tests\Compiler;

use Fel\Compiler\Compiler;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end native compilation: FEL source -> LLVM IR -> clang -> binary -> run.
 * Asserts the executed binary's stdout. Skipped when clang is unavailable.
 *
 * Covers only the numeric subset the CodeGen backend currently supports
 * (integer/float arithmetic, variables, if/else, while, display of numbers).
 * String concatenation and generic builtins (to_str, len, ...) are
 * interpreter-only and intentionally NOT exercised here.
 */
final class NativeCompileTest extends TestCase
{
    private static ?string $clang   = null;
    private string         $runtime;
    /** @var string[] paths to clean up */
    private array          $tmpFiles = [];

    public static function setUpBeforeClass(): void
    {
        $which = trim((string) @shell_exec('command -v clang 2>/dev/null'));
        self::$clang = $which !== '' ? $which : null;
    }

    protected function setUp(): void
    {
        if (self::$clang === null) {
            self::markTestSkipped('clang not installed — skipping native compilation tests');
        }
        $this->runtime = dirname(__DIR__, 2) . '/src/Compiler/Runtime/fel_runtime.c';
        self::assertFileExists($this->runtime, 'C runtime source must exist');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) @unlink($f);
        }
        $this->tmpFiles = [];
    }

    /** Compile FEL source to a native binary, run it, return stdout. */
    private function compileAndRun(string $src): string
    {
        $ir  = (new Compiler())->compileFile($src);
        $base = tempnam(sys_get_temp_dir(), 'fel_');
        $this->tmpFiles[] = $base;

        $llFile  = $base . '.ll';
        $binFile = $base . '.bin';
        $this->tmpFiles[] = $llFile;
        $this->tmpFiles[] = $binFile;

        file_put_contents($llFile, $ir);

        $cmd = sprintf(
            '%s -O2 %s %s -o %s 2>&1',
            escapeshellarg(self::$clang),
            escapeshellarg($llFile),
            escapeshellarg($this->runtime),
            escapeshellarg($binFile)
        );
        $clangOut = (string) shell_exec($cmd);
        self::assertFileExists($binFile, "clang failed to produce a binary:\n{$clangOut}");

        return (string) shell_exec(escapeshellarg($binFile) . ' 2>&1');
    }

    public function test_integer_arithmetic(): void
    {
        $out = $this->compileAndRun(
            "let a = 7; let b = 3;\n" .
            "display(a + b); display(a - b); display(a * b);\n" .
            "display(a / b); display(a % b);"
        );
        self::assertSame("10\n4\n21\n2\n1\n", $out);
    }

    public function test_float_arithmetic(): void
    {
        // 2.0 exercises the IEEE-754 hex float-literal encoding (regression:
        // "double 2" was rejected by LLVM before the hex fix).
        $out = $this->compileAndRun("let r = 2.0; display(r * r);");
        self::assertSame("4\n", $out);
    }

    public function test_float_with_fraction(): void
    {
        $out = $this->compileAndRun("display(3.14 * 2.0);");
        self::assertSame("6.28\n", $out);
    }

    public function test_while_loop_sum(): void
    {
        $out = $this->compileAndRun(
            "let sum = 0; let i = 1;\n" .
            "while (i <= 5) { sum = sum + i; i = i + 1; }\n" .
            "display(sum);"
        );
        self::assertSame("15\n", $out);
    }

    public function test_if_else_branch(): void
    {
        $out = $this->compileAndRun(
            "let n = 17;\n" .
            "if (n % 2 == 0) { display(100); } else { display(200); }"
        );
        self::assertSame("200\n", $out);
    }

    public function test_binary_exits_zero(): void
    {
        // A clean run: the @main wrapper returns the i32 from @fel_main (0).
        $ir   = (new Compiler())->compileFile('display(1);');
        $base = tempnam(sys_get_temp_dir(), 'fel_');
        $this->tmpFiles[] = $base;
        $ll   = $base . '.ll';
        $bin  = $base . '.bin';
        $this->tmpFiles[] = $ll;
        $this->tmpFiles[] = $bin;

        file_put_contents($ll, $ir);
        shell_exec(sprintf(
            '%s -O2 %s %s -o %s 2>&1',
            escapeshellarg(self::$clang),
            escapeshellarg($ll),
            escapeshellarg($this->runtime),
            escapeshellarg($bin)
        ));
        self::assertFileExists($bin);

        $code = 1;
        $lines = [];
        exec(escapeshellarg($bin), $lines, $code);
        self::assertSame(0, $code, 'native binary should exit 0');
        self::assertSame(['1'], $lines);
    }
}
