<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Object\FelObject;
use Fel\Object\Type\{StringObject, ErrorObject};

final class StringInfix implements InfixOperation {
    public function __construct(private readonly Values $values) {}

    public function supports(FelObject $l, FelObject $r): bool {
        return $l instanceof StringObject && $r instanceof StringObject;
    }

    public function apply(string $op, FelObject $l, FelObject $r): FelObject {
        /** @var StringObject $l */ /** @var StringObject $r */
        return match($op) {
            '+'  => new StringObject($l->value . $r->value),
            '==' => $this->values->bool($l->value === $r->value),
            '!=' => $this->values->bool($l->value !== $r->value),
            '<'  => $this->values->bool($l->value <  $r->value),
            '>'  => $this->values->bool($l->value >  $r->value),
            default => new ErrorObject("unknown operator: STRING {$op} STRING"),
        };
    }
}
