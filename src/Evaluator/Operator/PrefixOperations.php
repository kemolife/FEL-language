<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Object\FelObject;
use Fel\Object\Type\{IntegerObject, FloatObject, BooleanObject, NullObject, ErrorObject};

final class PrefixOperations {
    public function __construct(private readonly Values $values) {}

    public function evaluate(string $op, FelObject $operand): FelObject {
        return match($op) {
            '!'     => $this->bang($operand),
            '-'     => $this->minus($operand),
            default => new ErrorObject("unknown operator: {$op}{$operand->type()->value}"),
        };
    }

    private function bang(FelObject $o): BooleanObject {
        return match(true) {
            $o === $this->values->true()  => $this->values->false(),
            $o === $this->values->false() => $this->values->true(),
            $o instanceof NullObject      => $this->values->true(),
            default                       => $this->values->false(),
        };
    }

    private function minus(FelObject $o): FelObject {
        return match(true) {
            $o instanceof IntegerObject => new IntegerObject(-$o->value),
            $o instanceof FloatObject   => new FloatObject(-$o->value),
            default => new ErrorObject("unknown operator: -{$o->type()->value}"),
        };
    }
}
