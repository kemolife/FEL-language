<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Evaluator\Operator\IntegerInfix;
use Fel\Object\Type\{IntegerObject, BooleanObject, ErrorObject, StringObject};
use PHPUnit\Framework\TestCase;

final class IntegerInfixTest extends TestCase {
    private IntegerInfix $op;
    protected function setUp(): void { $this->op = new IntegerInfix(Values::default()); }

    public function test_supports_only_two_integers(): void {
        $this->assertTrue($this->op->supports(new IntegerObject(1), new IntegerObject(2)));
        $this->assertFalse($this->op->supports(new IntegerObject(1), new StringObject('x')));
    }
    public function test_arithmetic(): void {
        $r = $this->op->apply('+', new IntegerObject(5), new IntegerObject(3));
        $this->assertInstanceOf(IntegerObject::class, $r);
        $this->assertSame(8, $r->value);
        $this->assertSame(2,  $this->op->apply('-', new IntegerObject(5), new IntegerObject(3))->value);
        $this->assertSame(15, $this->op->apply('*', new IntegerObject(5), new IntegerObject(3))->value);
        $this->assertSame(2,  $this->op->apply('/', new IntegerObject(7), new IntegerObject(3))->value);
        $this->assertSame(1,  $this->op->apply('%', new IntegerObject(7), new IntegerObject(3))->value);
    }
    public function test_division_by_zero_is_error(): void {
        $r = $this->op->apply('/', new IntegerObject(1), new IntegerObject(0));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('division by zero', $r->message);
    }
    public function test_comparisons(): void {
        $this->assertTrue($this->op->apply('<', new IntegerObject(1), new IntegerObject(2))->value);
        $this->assertTrue($this->op->apply('==', new IntegerObject(2), new IntegerObject(2))->value);
        $this->assertInstanceOf(BooleanObject::class, $this->op->apply('>=', new IntegerObject(2), new IntegerObject(2)));
    }
    public function test_unknown_operator(): void {
        $r = $this->op->apply('^', new IntegerObject(1), new IntegerObject(2));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('unknown operator: INTEGER ^ INTEGER', $r->message);
    }
}
