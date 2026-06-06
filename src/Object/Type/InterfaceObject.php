<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

/** An interface contract (`interface Shape { area, perimeter }`). */
final class InterfaceObject implements FelObject {
    /** @param string[] $methods required method names */
    public function __construct(
        public readonly string $name,
        public readonly array  $methods,
    ) {}

    public function type(): ObjectType { return ObjectType::INTERFACE; }
    public function inspect(): string {
        return "interface {$this->name} { " . implode(', ', $this->methods) . ' }';
    }
}
