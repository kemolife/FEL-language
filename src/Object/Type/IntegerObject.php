<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType, Hashable};

final class IntegerObject implements FelObject, Hashable {
    public function __construct(public readonly int $value) {}
    public function type(): ObjectType { return ObjectType::INTEGER; }
    public function inspect(): string  { return (string)$this->value; }
    public function hashKey(): string  { return 'int:' . $this->value; }
}
