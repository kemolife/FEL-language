<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Lexer\Lexer;
use Fel\Token\{Token, TokenType};

final class TokenStream {
    private Token $curToken;
    private Token $peekToken;

    public function __construct(private readonly Lexer $lexer) {
        $this->curToken  = $this->lexer->nextToken();
        $this->peekToken = $this->lexer->nextToken();
    }

    public function cur(): Token  { return $this->curToken; }
    public function peek(): Token { return $this->peekToken; }

    public function next(): void {
        $this->curToken  = $this->peekToken;
        $this->peekToken = $this->lexer->nextToken();
    }

    public function curIs(TokenType $t): bool  { return $this->curToken->type  === $t; }
    public function peekIs(TokenType $t): bool { return $this->peekToken->type === $t; }
}
