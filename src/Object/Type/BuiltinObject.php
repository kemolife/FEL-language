<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class BuiltinObject implements FelObject {
    public function __construct(public readonly \Closure $fn) {}
    public function type(): ObjectType { return ObjectType::BUILTIN; }
    public function inspect(): string  { return 'builtin function'; }
}
