<?php
declare(strict_types=1);
namespace Fel\Object\Type;

use Fel\Object\FelObject;

final class HashPair {
    public function __construct(
        public readonly FelObject $key,
        public readonly FelObject $value,
    ) {}
}
