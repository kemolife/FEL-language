<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType, Hashable};

final class BooleanObject implements FelObject, Hashable {
    public function __construct(public readonly bool $value) {}
    public function type(): ObjectType { return ObjectType::BOOLEAN; }
    public function inspect(): string  { return $this->value ? 'true' : 'false'; }
    public function hashKey(): string  { return 'bool:' . ($this->value ? '1' : '0'); }
}
