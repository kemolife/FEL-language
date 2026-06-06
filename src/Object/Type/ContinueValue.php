<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

/** Loop-control signal produced by a `continue` statement. */
final class ContinueValue implements FelObject {
    public function type(): ObjectType { return ObjectType::CONTINUE; }
    public function inspect(): string  { return 'continue'; }
}
