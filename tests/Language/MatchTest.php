<?php
declare(strict_types=1);
namespace Fel\Tests\Language;

use Fel\Engine;
use PHPUnit\Framework\TestCase;

final class MatchTest extends TestCase
{
    private function eval(string $src): mixed
    {
        $engine = new Engine();
        $result = $engine->eval($src);
        self::assertFalse($engine->hasErrors(), implode(', ', $engine->errors()));
        return $result;
    }

    public function test_match_first_arm(): void
    {
        self::assertSame('one', $this->eval('match (1) { 1 => "one", 2 => "two", _ => "other" }'));
    }

    public function test_match_middle_arm(): void
    {
        self::assertSame('two', $this->eval('match (2) { 1 => "one", 2 => "two", _ => "other" }'));
    }

    public function test_match_wildcard(): void
    {
        self::assertSame('other', $this->eval('match (99) { 1 => "one", 2 => "two", _ => "other" }'));
    }

    public function test_match_string_subject(): void
    {
        self::assertSame(2, $this->eval('match ("b") { "a" => 1, "b" => 2, _ => 0 }'));
    }

    public function test_match_no_match_no_wildcard_returns_null(): void
    {
        self::assertNull($this->eval('match (5) { 1 => "one" }'));
    }

    public function test_match_arms_evaluate_expressions(): void
    {
        self::assertSame(6, $this->eval('let x = 3; match (x) { 3 => x * 2, _ => 0 }'));
    }
}
