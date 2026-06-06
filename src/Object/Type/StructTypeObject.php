<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

/**
 * A struct type declaration (`struct Point { x, y }`).
 * Methods are attached after declaration via method definitions and stored
 * by name as ['recvVar' => string, 'fn' => FunctionObject].
 */
final class StructTypeObject implements FelObject {
    /** @var array<string, array{recvVar: string, fn: FunctionObject}> */
    public array $methods = [];

    /** @param string[] $fields */
    public function __construct(
        public readonly string $name,
        public readonly array  $fields,
    ) {}

    public function hasField(string $name): bool {
        return in_array($name, $this->fields, true);
    }

    public function type(): ObjectType { return ObjectType::STRUCT_TYPE; }
    public function inspect(): string {
        return "struct {$this->name} { " . implode(', ', $this->fields) . ' }';
    }
}
