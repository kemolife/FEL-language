<?php
declare(strict_types=1);
namespace Fel\Tests\Evaluator;

use Fel\Evaluator\Values;
use Fel\Object\Type\{IntegerObject, NullObject, StringObject};
use PHPUnit\Framework\TestCase;

final class ValuesTest extends TestCase {
    public function test_singletons_are_identical(): void {
        $v = Values::default();
        $this->assertSame($v->true(), $v->true());
        $this->assertSame($v->false(), $v->false());
        $this->assertSame($v->null(), $v->null());
    }

    public function test_bool_returns_canonical_singletons(): void {
        $v = Values::default();
        $this->assertSame($v->true(), $v->bool(true));
        $this->assertSame($v->false(), $v->bool(false));
    }

    public function test_is_truthy_table(): void {
        $v = Values::default();
        $this->assertFalse($v->isTruthy($v->null()));
        $this->assertFalse($v->isTruthy($v->false()));
        $this->assertTrue($v->isTruthy($v->true()));
        $this->assertTrue($v->isTruthy(new IntegerObject(0)));
        $this->assertTrue($v->isTruthy(new StringObject('')));
    }
}
