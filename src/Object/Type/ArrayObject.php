<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class ArrayObject implements FelObject {
    /** @param FelObject[] $elements */
    public function __construct(public readonly array $elements) {}
    public function type(): ObjectType { return ObjectType::ARRAY; }
    public function inspect(): string {
        $els = implode(', ', array_map(fn($e) => $e->inspect(), $this->elements));
        return "[{$els}]";
    }
}
