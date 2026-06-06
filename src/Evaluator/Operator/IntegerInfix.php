<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Object\FelObject;
use Fel\Object\Type\{IntegerObject, ErrorObject};

final class IntegerInfix implements InfixOperation {
    public function __construct(private readonly Values $values) {}

    public function supports(FelObject $l, FelObject $r): bool {
        return $l instanceof IntegerObject && $r instanceof IntegerObject;
    }

    public function apply(string $op, FelObject $l, FelObject $r): FelObject {
        /** @var IntegerObject $l */ /** @var IntegerObject $r */
        return match($op) {
            '+'  => new IntegerObject($l->value + $r->value),
            '-'  => new IntegerObject($l->value - $r->value),
            '*'  => new IntegerObject($l->value * $r->value),
            '/'  => $r->value === 0 ? new ErrorObject('division by zero') : new IntegerObject(intdiv($l->value, $r->value)),
            '%'  => $r->value === 0 ? new ErrorObject('division by zero') : new IntegerObject($l->value % $r->value),
            '<'  => $this->values->bool($l->value <  $r->value),
            '>'  => $this->values->bool($l->value >  $r->value),
            '<=' => $this->values->bool($l->value <= $r->value),
            '>=' => $this->values->bool($l->value >= $r->value),
            '==' => $this->values->bool($l->value === $r->value),
            '!=' => $this->values->bool($l->value !== $r->value),
            default => new ErrorObject("unknown operator: INTEGER {$op} INTEGER"),
        };
    }
}
