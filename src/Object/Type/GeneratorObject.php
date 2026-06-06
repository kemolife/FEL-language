<?php
declare(strict_types=1);
namespace Fel\Object\Type;

use Fel\Object\{FelObject, ObjectType};

final class GeneratorObject implements FelObject {
    /** @param \Closure(): \Iterator $factory produces a fresh iterator of FelObject */
    public function __construct(
        public readonly \Closure $factory,
        public readonly string   $label = 'generator',
    ) {}

    public function type(): ObjectType { return ObjectType::GENERATOR; }
    public function inspect(): string  { return "<{$this->label}>"; }

    /** Get a fresh iterator. */
    public function iterator(): \Iterator {
        return ($this->factory)();
    }
}
