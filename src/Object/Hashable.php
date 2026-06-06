<?php
declare(strict_types=1);
namespace Fel\Object;

interface Hashable {
    public function hashKey(): string;
}
