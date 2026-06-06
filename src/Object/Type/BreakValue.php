<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

/** Loop-control signal produced by a `break` statement. */
final class BreakValue implements FelObject {
    public function type(): ObjectType { return ObjectType::BREAK; }
    public function inspect(): string  { return 'break'; }
}
