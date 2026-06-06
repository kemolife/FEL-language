<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Object\FelObject;
use Fel\Object\Type\ErrorObject;

final class InfixOperations {
    /** @param InfixOperation[] $operations */
    public function __construct(
        private readonly Values $values,
        private readonly array  $operations,
    ) {}

    public function evaluate(string $op, FelObject $l, FelObject $r): FelObject {
        foreach ($this->operations as $operation) {
            if ($operation->supports($l, $r)) {
                return $operation->apply($op, $l, $r);
            }
        }
        if ($op === '==') return $this->values->bool($l === $r);
        if ($op === '!=') return $this->values->bool($l !== $r);
        if ($l->type() !== $r->type()) {
            return new ErrorObject("type mismatch: {$l->type()->value} {$op} {$r->type()->value}");
        }
        return new ErrorObject("unknown operator: {$l->type()->value} {$op} {$r->type()->value}");
    }
}
