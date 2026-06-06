<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class AssignmentTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_plus_assign(): void
    {
        self::assertSame(15, $this->eval('let x = 10; x += 5; x'));
    }

    public function test_minus_assign(): void
    {
        self::assertSame(7, $this->eval('let x = 10; x -= 3; x'));
    }

    public function test_times_assign(): void
    {
        self::assertSame(20, $this->eval('let x = 10; x *= 2; x'));
    }

    public function test_div_assign(): void
    {
        self::assertSame(5, $this->eval('let x = 10; x /= 2; x'));
    }

    public function test_mod_assign(): void
    {
        self::assertSame(1, $this->eval('let x = 10; x %= 3; x'));
    }

    public function test_plus_assign_string(): void
    {
        self::assertSame('ab', $this->eval('let s = "a"; s += "b"; s'));
    }

    public function test_compound_assign_in_loop(): void
    {
        self::assertSame(10, $this->eval('let s = 0; for (x in [1,2,3,4]) { s += x; } s'));
    }
}
