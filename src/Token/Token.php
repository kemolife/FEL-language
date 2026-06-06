<?php
declare(strict_types=1);
namespace Fel\Token;

final readonly class Token {
    public function __construct(
        public TokenType $type,
        public string    $literal,
        public int       $line = 0,
        public int       $col  = 0,
    ) {}

    public function __toString(): string {
        return "Token({$this->type->value}, {$this->literal})";
    }
}
