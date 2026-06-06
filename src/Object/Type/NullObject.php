<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class NullObject implements FelObject {
    public function type(): ObjectType { return ObjectType::NULL; }
    public function inspect(): string  { return 'null'; }
}
