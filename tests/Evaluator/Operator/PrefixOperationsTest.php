<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Evaluator\Operator\PrefixOperations;
use Fel\Object\Type\{IntegerObject, FloatObject, StringObject, ErrorObject};
use PHPUnit\Framework\TestCase;

final class PrefixOperationsTest extends TestCase {
    private Values $values;
    private PrefixOperations $prefix;

    protected function setUp(): void {
        $this->values = Values::default();
        $this->prefix = new PrefixOperations($this->values);
    }

    public function test_bang_negates_truthiness(): void {
        $this->assertSame($this->values->false(), $this->prefix->evaluate('!', $this->values->true()));
        $this->assertSame($this->values->true(),  $this->prefix->evaluate('!', $this->values->false()));
        $this->assertSame($this->values->true(),  $this->prefix->evaluate('!', $this->values->null()));
        $this->assertSame($this->values->false(), $this->prefix->evaluate('!', new IntegerObject(5)));
    }

    public function test_minus_negates_numbers(): void {
        $this->assertSame(-5,   $this->prefix->evaluate('-', new IntegerObject(5))->value);
        $this->assertSame(-1.5, $this->prefix->evaluate('-', new FloatObject(1.5))->value);
    }

    public function test_minus_on_non_number_is_error(): void {
        $r = $this->prefix->evaluate('-', new StringObject('x'));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('unknown operator: -STRING', $r->message);
    }

    public function test_unknown_prefix_operator(): void {
        $r = $this->prefix->evaluate('~', new IntegerObject(1));
        $this->assertInstanceOf(ErrorObject::class, $r);
        $this->assertSame('unknown operator: ~INTEGER', $r->message);
    }
}
