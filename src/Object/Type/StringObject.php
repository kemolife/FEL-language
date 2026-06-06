<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType, Hashable};

final class StringObject implements FelObject, Hashable {
    public function __construct(public readonly string $value) {}
    public function type(): ObjectType { return ObjectType::STRING; }
    public function inspect(): string  { return $this->value; }
    public function hashKey(): string  { return 'str:' . $this->value; }
}
