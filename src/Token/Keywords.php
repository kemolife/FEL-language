<?php
declare(strict_types=1);
namespace Fel\Token;

final class Keywords {
    private const MAP = [
        'fn'       => TokenType::FUNCTION,
        'let'      => TokenType::LET,
        'true'     => TokenType::TRUE,
        'false'    => TokenType::FALSE,
        'if'       => TokenType::IF,
        'else'     => TokenType::ELSE,
        'return'   => TokenType::RETURN,
        'while'    => TokenType::WHILE,
        'for'      => TokenType::FOR,
        'in'       => TokenType::IN,
        'break'    => TokenType::BREAK,
        'continue' => TokenType::CONTINUE,
        'import'   => TokenType::IMPORT,
        'null'     => TokenType::NULL,
    ];

    public static function lookup(string $ident): TokenType {
        return self::MAP[$ident] ?? TokenType::IDENT;
    }
}
