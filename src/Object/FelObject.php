<?php
declare(strict_types=1);
namespace Fel\Object;

interface FelObject {
    public function type(): ObjectType;
    public function inspect(): string;
}
