<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Lexer\Lexer;

final class ParserFactory {
    public static function fromSource(string $source): Parser {
        return new Parser(new TokenStream(new Lexer($source)), new Precedence());
    }
}
