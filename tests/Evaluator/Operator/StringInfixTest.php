<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Evaluator\Operator\StringInfix;
use Fel\Object\Type\{StringObject, IntegerObject, ErrorObject};
use PHPUnit\Framework\TestCase;

final class StringInfixTest extends TestCase {
    private StringInfix $op;
    protected function setUp(): void { $this->op = new StringInfix(Values::default()); }

    public function test_supports_only_two_strings(): void {
        $this->assertTrue($this->op->supports(new StringObject('a'), new StringObject('b')));
        $this->assertFalse($this->op->supports(new StringObject('a'), new IntegerObject(1)));
    }
    public function test_concat(): void {
        $r = $this->op->apply('+', new StringObject('foo'), new StringObject('bar'));
        $this->assertInstanceOf(StringObject::class, $r);
        $this->assertSame('foobar', $r->value);
    }
    public function test_comparisons(): void {
        $this->assertTrue($this->op->apply('==', new StringObject('a'), new StringObject('a'))->value);
        $this->assertTrue($this->op->apply('<',  new StringObject('a'), new StringObject('b'))->value);
    }
    public function test_unknown_operator(): void {
        $r = $this->op->apply('*', new StringObject('a'), new StringObject('b'));
        $this->assertSame('unknown operator: STRING * STRING', $r->message);
    }
}
