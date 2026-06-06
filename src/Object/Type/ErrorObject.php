<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};

final class ErrorObject implements FelObject {
    public function __construct(
        public readonly string $message,
        public readonly int    $line = 0,
    ) {}
    public function type(): ObjectType { return ObjectType::ERROR; }
    public function inspect(): string  {
        $loc = $this->line > 0 ? " (line {$this->line})" : '';
        return "ERROR: {$this->message}{$loc}";
    }
}
