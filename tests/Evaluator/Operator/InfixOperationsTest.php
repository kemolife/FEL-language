<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Evaluator\Operator\{InfixOperations, IntegerInfix, FloatInfix, StringInfix};
use Fel\Object\Type\{IntegerObject, FloatObject, StringObject, BooleanObject, ErrorObject};
use PHPUnit\Framework\TestCase;

final class InfixOperationsTest extends TestCase {
    private Values $values;
    private InfixOperations $infix;

    protected function setUp(): void {
        $this->values = Values::default();
        $this->infix  = new InfixOperations($this->values, [
            new IntegerInfix($this->values),
            new FloatInfix($this->values),
            new StringInfix($this->values),
        ]);
    }
    public function test_dispatches_integers(): void {
        $r = $this->infix->evaluate('+', new IntegerObject(2), new IntegerObject(3));
        $this->assertSame(5, $r->value);
    }
    public function test_dispatches_float_when_mixed(): void {
        $r = $this->infix->evaluate('+', new IntegerObject(2), new FloatObject(0.5));
        $this->assertInstanceOf(FloatObject::class, $r);
        $this->assertSame(2.5, $r->value);
    }
    public function test_reference_equality_for_unmatched_types(): void {
        $t = $this->values->true();
        $this->assertSame($this->values->true(),  $this->infix->evaluate('==', $t, $t));
        $this->assertSame($this->values->false(), $this->infix->evaluate('!=', $t, $t));
    }
    public function test_type_mismatch_error(): void {
        $r = $this->infix->evaluate('+', new IntegerObject(1), new StringObject('x'));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('type mismatch: INTEGER + STRING', $r->message);
    }
    public function test_unknown_operator_same_types_non_arithmetic(): void {
        $t = $this->values->true();
        $r = $this->infix->evaluate('+', $t, $t);
        $this->assertSame('unknown operator: BOOLEAN + BOOLEAN', $r->message);
    }
}
