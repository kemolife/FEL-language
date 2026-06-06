<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class ControlFlowTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_break_exits_while(): void
    {
        self::assertSame(3, $this->eval(
            'let i = 0; while (true) { if (i == 3) { break; } i = i + 1; } i'
        ));
    }

    public function test_continue_skips_iteration_in_while(): void
    {
        // sum 1..5 skipping 3 => 1+2+4+5 = 12
        self::assertSame(12, $this->eval(
            'let s = 0; let i = 0; while (i < 5) { i = i + 1; if (i == 3) { continue; } s = s + i; } s'
        ));
    }

    public function test_break_exits_for_in(): void
    {
        self::assertSame(3, $this->eval(
            'let s = 0; for (x in [1,2,3,4]) { if (x == 3) { break; } s = s + x; } s'
        ));
    }

    public function test_continue_skips_iteration_in_for_in(): void
    {
        // 1+3+4 = 8 (skip 2)
        self::assertSame(8, $this->eval(
            'let s = 0; for (x in [1,2,3,4]) { if (x == 2) { continue; } s = s + x; } s'
        ));
    }

    public function test_else_if_first_branch(): void
    {
        self::assertSame('a', $this->eval(
            'let x = 1; if (x == 1) { "a" } else if (x == 2) { "b" } else { "c" }'
        ));
    }

    public function test_else_if_middle_branch(): void
    {
        self::assertSame('b', $this->eval(
            'let x = 2; if (x == 1) { "a" } else if (x == 2) { "b" } else { "c" }'
        ));
    }

    public function test_else_if_final_else(): void
    {
        self::assertSame('c', $this->eval(
            'let x = 9; if (x == 1) { "a" } else if (x == 2) { "b" } else { "c" }'
        ));
    }

    public function test_else_if_chained_multiple(): void
    {
        self::assertSame('three', $this->eval(
            'let x = 3; if (x == 1) { "one" } else if (x == 2) { "two" } else if (x == 3) { "three" } else { "many" }'
        ));
    }
}
