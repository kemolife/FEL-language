<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Token\TokenType;

final class Precedence {
    public const LOWEST       = 1;
    public const OR           = 2;
    public const AND          = 3;
    public const EQUALS       = 4;
    public const LESS_GREATER = 5;
    public const SUM          = 6;
    public const PRODUCT      = 7;
    public const PREFIX       = 8;
    public const CALL         = 9;
    public const INDEX        = 10;

    private const TABLE = [
        TokenType::OR->value       => self::OR,
        TokenType::AND->value      => self::AND,
        TokenType::EQ->value       => self::EQUALS,
        TokenType::NOT_EQ->value   => self::EQUALS,
        TokenType::LT->value       => self::LESS_GREATER,
        TokenType::GT->value       => self::LESS_GREATER,
        TokenType::LT_EQ->value    => self::LESS_GREATER,
        TokenType::GT_EQ->value    => self::LESS_GREATER,
        TokenType::PLUS->value     => self::SUM,
        TokenType::MINUS->value    => self::SUM,
        TokenType::SLASH->value    => self::PRODUCT,
        TokenType::ASTERISK->value => self::PRODUCT,
        TokenType::PERCENT->value  => self::PRODUCT,
        TokenType::LPAREN->value   => self::CALL,
        TokenType::LBRACKET->value => self::INDEX,
        TokenType::DOT->value      => self::INDEX,
    ];

    public function of(TokenType $t): int {
        return self::TABLE[$t->value] ?? self::LOWEST;
    }
}
