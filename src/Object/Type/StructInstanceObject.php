<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

/** A concrete struct value (`Point{x: 1, y: 2}`). */
final class StructInstanceObject implements FelObject {
    /** @param array<string, FelObject> $fields */
    public function __construct(
        public readonly StructTypeObject $structType,
        public readonly array            $fields,
    ) {}

    public function type(): ObjectType { return ObjectType::STRUCT; }
    public function inspect(): string {
        $parts = [];
        foreach ($this->fields as $name => $val) {
            $parts[] = "{$name}: {$val->inspect()}";
        }
        return "{$this->structType->name}{" . implode(', ', $parts) . '}';
    }
}
