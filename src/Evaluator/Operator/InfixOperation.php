<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Object\FelObject;

interface InfixOperation {
    public function supports(FelObject $l, FelObject $r): bool;
    public function apply(string $op, FelObject $l, FelObject $r): FelObject;
}
