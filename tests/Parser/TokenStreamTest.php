<?php
declare(strict_types=1);
namespace Fel\Tests\Parser;

use Fel\Lexer\Lexer;
use Fel\Parser\TokenStream;
use Fel\Token\TokenType;
use PHPUnit\Framework\TestCase;

final class TokenStreamTest extends TestCase {
    public function test_primes_cur_and_peek(): void {
        $s = new TokenStream(new Lexer('1 + 2'));
        $this->assertSame(TokenType::INT,  $s->cur()->type);
        $this->assertSame('1', $s->cur()->literal);
        $this->assertSame(TokenType::PLUS, $s->peek()->type);
    }
    public function test_next_advances(): void {
        $s = new TokenStream(new Lexer('1 + 2'));
        $s->next();
        $this->assertSame(TokenType::PLUS, $s->cur()->type);
        $this->assertSame(TokenType::INT,  $s->peek()->type);
        $this->assertSame('2', $s->peek()->literal);
    }
    public function test_predicates(): void {
        $s = new TokenStream(new Lexer('1 + 2'));
        $this->assertTrue($s->curIs(TokenType::INT));
        $this->assertTrue($s->peekIs(TokenType::PLUS));
        $this->assertFalse($s->curIs(TokenType::PLUS));
    }
    public function test_reaches_eof(): void {
        $s = new TokenStream(new Lexer('1'));
        $this->assertSame(TokenType::INT, $s->cur()->type);
        $this->assertSame(TokenType::EOF, $s->peek()->type);
        $s->next();
        $this->assertSame(TokenType::EOF, $s->cur()->type);
    }

    public function test_next_past_eof_stays_eof(): void {
        $s = new TokenStream(new Lexer('1'));
        $s->next();                                  // cur = EOF
        $this->assertSame(TokenType::EOF, $s->cur()->type);
        $s->next();
        $s->next();
        $this->assertSame(TokenType::EOF, $s->cur()->type);  // repeated next() stays EOF, no advance to ILLEGAL/throw
    }
}
