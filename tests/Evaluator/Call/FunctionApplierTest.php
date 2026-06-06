<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Call;

use Fel\Evaluator\Values;
use Fel\Evaluator\Call\FunctionApplier;
use Fel\Object\Environment;
use Fel\Object\Type\{FunctionObject, BuiltinObject, IntegerObject, ErrorObject, NullObject, ReturnValue};
use Fel\Object\FelObject;
use PHPUnit\Framework\TestCase;

final class FunctionApplierTest extends TestCase {
    private Values $values;
    private FunctionApplier $applier;

    protected function setUp(): void {
        $this->values  = Values::default();
        $this->applier = new FunctionApplier($this->values);
    }

    public function test_applies_closure_binding_params(): void {
        $env = new Environment();
        $fn  = new FunctionObject(
            params:  ['x'],
            body:    fn(Environment $e): FelObject => $e->get('x'),
            bodySrc: 'x',
            env:     $env,
        );
        $r = $this->applier->apply($fn, [new IntegerObject(42)]);
        $this->assertInstanceOf(IntegerObject::class, $r);
        $this->assertSame(42, $r->value);
    }

    public function test_missing_args_padded_with_null(): void {
        $env = new Environment();
        $fn  = new FunctionObject(
            params:  ['x'],
            body:    fn(Environment $e): FelObject => $e->get('x'),
            bodySrc: 'x',
            env:     $env,
        );
        $r = $this->applier->apply($fn, []);
        $this->assertInstanceOf(NullObject::class, $r);
    }

    public function test_invokes_builtin(): void {
        $builtin = new BuiltinObject(fn(FelObject ...$a): FelObject => new IntegerObject(count($a)));
        $r = $this->applier->apply($builtin, [new IntegerObject(1), new IntegerObject(2)]);
        $this->assertSame(2, $r->value);
    }

    public function test_return_value_is_unwrapped(): void {
        $env   = new Environment();
        $inner = new IntegerObject(7);
        $fn    = new FunctionObject(
            params:  [],
            body:    fn(Environment $e): FelObject => new ReturnValue($inner),
            bodySrc: 'return 7',
            env:     $env,
        );
        $r = $this->applier->apply($fn, []);
        $this->assertSame($inner, $r);
    }

    public function test_not_a_function_is_error(): void {
        $r = $this->applier->apply(new IntegerObject(1), []);
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('not a function: INTEGER', $r->message);
    }
}
