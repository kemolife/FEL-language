<?php
declare(strict_types=1);
namespace Fel\Tests\Parser;

use Fel\Parser\Precedence;
use Fel\Token\TokenType;
use PHPUnit\Framework\TestCase;

final class PrecedenceTest extends TestCase {
    public function test_known_operators(): void {
        $p = new Precedence();
        $this->assertSame(Precedence::SUM,     $p->of(TokenType::PLUS));
        $this->assertSame(Precedence::PRODUCT, $p->of(TokenType::ASTERISK));
        $this->assertSame(Precedence::CALL,    $p->of(TokenType::LPAREN));
        $this->assertSame(Precedence::INDEX,   $p->of(TokenType::LBRACKET));
        $this->assertSame(Precedence::INDEX,   $p->of(TokenType::DOT));
    }
    public function test_product_binds_tighter_than_sum(): void {
        $p = new Precedence();
        $this->assertGreaterThan($p->of(TokenType::PLUS), $p->of(TokenType::ASTERISK));
    }
    public function test_unknown_token_is_lowest(): void {
        $p = new Precedence();
        $this->assertSame(Precedence::LOWEST, $p->of(TokenType::SEMICOLON));
    }

    public function test_all_table_entries(): void {
        $p = new Precedence();
        $this->assertSame(Precedence::OR,           $p->of(TokenType::OR));
        $this->assertSame(Precedence::AND,          $p->of(TokenType::AND));
        $this->assertSame(Precedence::EQUALS,       $p->of(TokenType::EQ));
        $this->assertSame(Precedence::EQUALS,       $p->of(TokenType::NOT_EQ));
        $this->assertSame(Precedence::LESS_GREATER, $p->of(TokenType::LT));
        $this->assertSame(Precedence::LESS_GREATER, $p->of(TokenType::GT));
        $this->assertSame(Precedence::LESS_GREATER, $p->of(TokenType::LT_EQ));
        $this->assertSame(Precedence::LESS_GREATER, $p->of(TokenType::GT_EQ));
        $this->assertSame(Precedence::SUM,          $p->of(TokenType::PLUS));
        $this->assertSame(Precedence::SUM,          $p->of(TokenType::MINUS));
        $this->assertSame(Precedence::PRODUCT,      $p->of(TokenType::SLASH));
        $this->assertSame(Precedence::PRODUCT,      $p->of(TokenType::ASTERISK));
        $this->assertSame(Precedence::PRODUCT,      $p->of(TokenType::PERCENT));
        $this->assertSame(Precedence::CALL,         $p->of(TokenType::LPAREN));
        $this->assertSame(Precedence::INDEX,        $p->of(TokenType::LBRACKET));
        $this->assertSame(Precedence::INDEX,        $p->of(TokenType::DOT));
    }

    public function test_full_precedence_chain_is_ordered(): void {
        $this->assertTrue(
            Precedence::LOWEST < Precedence::OR
            && Precedence::OR < Precedence::AND
            && Precedence::AND < Precedence::EQUALS
            && Precedence::EQUALS < Precedence::LESS_GREATER
            && Precedence::LESS_GREATER < Precedence::SUM
            && Precedence::SUM < Precedence::PRODUCT
            && Precedence::PRODUCT < Precedence::PREFIX
            && Precedence::PREFIX < Precedence::CALL
            && Precedence::CALL < Precedence::INDEX
        );
    }
}
