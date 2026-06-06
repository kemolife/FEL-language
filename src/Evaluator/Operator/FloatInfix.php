<?php
declare(strict_types=1);
namespace Fel\Evaluator\Operator;

use Fel\Evaluator\Values;
use Fel\Object\FelObject;
use Fel\Object\Type\{IntegerObject, FloatObject, ErrorObject};

final class FloatInfix implements InfixOperation {
    public function __construct(private readonly Values $values) {}

    public function supports(FelObject $l, FelObject $r): bool {
        return $l instanceof FloatObject || $r instanceof FloatObject;
    }

    public function apply(string $op, FelObject $l, FelObject $r): FelObject {
        $lv = $l instanceof FloatObject ? $l->value : ($l instanceof IntegerObject ? (float)$l->value : 0.0);
        $rv = $r instanceof FloatObject ? $r->value : ($r instanceof IntegerObject ? (float)$r->value : 0.0);
        return match($op) {
            '+'  => new FloatObject($lv + $rv),
            '-'  => new FloatObject($lv - $rv),
            '*'  => new FloatObject($lv * $rv),
            '/'  => $rv == 0.0 ? new ErrorObject('division by zero') : new FloatObject($lv / $rv),
            '%'  => $rv == 0.0 ? new ErrorObject('division by zero') : new FloatObject(fmod($lv, $rv)),
            '<'  => $this->values->bool($lv <  $rv),
            '>'  => $this->values->bool($lv >  $rv),
            '<=' => $this->values->bool($lv <= $rv),
            '>=' => $this->values->bool($lv >= $rv),
            '==' => $this->values->bool($lv == $rv),
            '!=' => $this->values->bool($lv != $rv),
            default => new ErrorObject("unknown operator: FLOAT {$op} FLOAT"),
        };
    }
}
