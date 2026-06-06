<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class ReturnValue implements FelObject {
    public function __construct(public readonly FelObject $value) {}
    public function type(): ObjectType { return ObjectType::RETURN; }
    public function inspect(): string  { return $this->value->inspect(); }
}
