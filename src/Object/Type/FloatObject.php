<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class FloatObject implements FelObject {
    public function __construct(public readonly float $value) {}
    public function type(): ObjectType { return ObjectType::FLOAT; }
    public function inspect(): string  {
        $s = (string)$this->value;
        return str_contains($s, '.') ? $s : $s . '.0';
    }
}
