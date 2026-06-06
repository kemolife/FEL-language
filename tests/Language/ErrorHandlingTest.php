<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class ErrorHandlingTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_try_without_error_returns_body_value(): void
    {
        self::assertSame(42, $this->eval('try { 42 } catch (e) { -1 }'));
    }

    public function test_throw_is_caught(): void
    {
        self::assertSame('caught', $this->eval('try { throw "boom"; 1 } catch (e) { "caught" }'));
    }

    public function test_catch_binds_thrown_message(): void
    {
        self::assertSame('boom', $this->eval('try { throw "boom"; } catch (e) { e }'));
    }

    public function test_runtime_error_is_caught(): void
    {
        // undefined identifier raises a runtime error, caught by try
        self::assertSame('recovered', $this->eval('try { unknown_var } catch (e) { "recovered" }'));
    }

    public function test_uncaught_error_surfaces(): void
    {
        $engine = new Engine();
        $result = $engine->eval('throw "fatal"');
        self::assertTrue($engine->hasErrors());
        self::assertNull($result);
    }
}
