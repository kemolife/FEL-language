<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Lexer\Lexer;
use Fel\Token\{Token, TokenType};

final class TokenStream {
    private Token $curToken;
    /** @var Token[] lookahead buffer; index 0 == peek (1 ahead) */
    private array $buffer = [];

    public function __construct(private readonly Lexer $lexer) {
        $this->curToken = $this->lexer->nextToken();
        $this->buffer[] = $this->lexer->nextToken();
    }

    public function cur(): Token  { return $this->curToken; }
    public function peek(): Token { return $this->peekAt(1); }

    /** Look n tokens ahead. peekAt(1) == peek(). EOF repeats past end. */
    public function peekAt(int $n): Token {
        while (count($this->buffer) < $n) {
            $this->buffer[] = $this->lexer->nextToken();
        }
        return $this->buffer[$n - 1];
    }

    public function next(): void {
        $this->curToken = array_shift($this->buffer);
        if (empty($this->buffer)) {
            $this->buffer[] = $this->lexer->nextToken();
        }
    }

    public function curIs(TokenType $t): bool  { return $this->curToken->type === $t; }
    public function peekIs(TokenType $t): bool { return $this->peek()->type === $t; }
}
