<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Evaluator\Operator\FloatInfix;
use Fel\Object\Type\{IntegerObject, FloatObject, ErrorObject};
use PHPUnit\Framework\TestCase;

final class FloatInfixTest extends TestCase {
    private FloatInfix $op;
    protected function setUp(): void { $this->op = new FloatInfix(Values::default()); }

    public function test_supports_when_either_side_is_float(): void {
        $this->assertTrue($this->op->supports(new FloatObject(1.0), new IntegerObject(2)));
        $this->assertTrue($this->op->supports(new IntegerObject(2), new FloatObject(1.0)));
        $this->assertFalse($this->op->supports(new IntegerObject(1), new IntegerObject(2)));
    }
    public function test_arithmetic_with_int_coercion(): void {
        $r = $this->op->apply('+', new FloatObject(1.5), new IntegerObject(2));
        $this->assertInstanceOf(FloatObject::class, $r);
        $this->assertSame(3.5, $r->value);
    }
    public function test_division_by_zero_is_error(): void {
        $r = $this->op->apply('/', new FloatObject(1.0), new FloatObject(0.0));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('division by zero', $r->message);
    }
    public function test_modulo_uses_fmod(): void {
        $r = $this->op->apply('%', new FloatObject(5.5), new FloatObject(2.0));
        $this->assertEqualsWithDelta(1.5, $r->value, 1e-9);
    }
    public function test_unknown_operator(): void {
        $r = $this->op->apply('^', new FloatObject(1.0), new FloatObject(2.0));
        $this->assertSame('unknown operator: FLOAT ^ FLOAT', $r->message);
    }
}
