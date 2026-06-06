<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class HashObject implements FelObject {
    /** @param array<string, HashPair> $pairs keyed by hashKey() */
    public function __construct(public readonly array $pairs) {}
    public function type(): ObjectType { return ObjectType::HASH; }
    public function inspect(): string {
        $parts = [];
        foreach ($this->pairs as $pair) {
            $parts[] = $pair->key->inspect() . ': ' . $pair->value->inspect();
        }
        return '{' . implode(', ', $parts) . '}';
    }
}
