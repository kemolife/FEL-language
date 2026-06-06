<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Ast\Node\{
    Program, LetStatement, AssignStatement, ReturnStatement,
    ExpressionStatement, BlockStatement,
    Identifier, IntegerLiteral, FloatLiteral, StringLiteral, BooleanLiteral, NullLiteral,
    PrefixExpression, InfixExpression,
    IfExpression, WhileExpression, ForInExpression,
    FunctionLiteral, CallExpression,
    ArrayLiteral, IndexExpression, HashLiteral,
    ImportStatement,
};
use Fel\Ast\{Expression, Statement};
use Fel\Token\{Token, TokenType};

final class Parser {
    private array  $errors = [];

    /** @var array<string, callable> */
    private array $prefixFns = [];
    /** @var array<string, callable> */
    private array $infixFns  = [];

    public function __construct(
        private readonly TokenStream $stream,
        private readonly Precedence  $precedence,
    ) {
        $this->registerPrefixes();
        $this->registerInfixes();
    }

    private function registerPrefixes(): void {
        $this->prefixFns[TokenType::IDENT->value]    = fn() => new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        $this->prefixFns[TokenType::INT->value]      = fn() => $this->parseIntegerLiteral();
        $this->prefixFns[TokenType::FLOAT->value]    = fn() => $this->parseFloatLiteral();
        $this->prefixFns[TokenType::STRING->value]   = fn() => new StringLiteral($this->stream->cur(), $this->stream->cur()->literal);
        $this->prefixFns[TokenType::TRUE->value]     = fn() => new BooleanLiteral($this->stream->cur(), true);
        $this->prefixFns[TokenType::FALSE->value]    = fn() => new BooleanLiteral($this->stream->cur(), false);
        $this->prefixFns[TokenType::NULL->value]     = fn() => new NullLiteral($this->stream->cur());
        $this->prefixFns[TokenType::BANG->value]     = fn() => $this->parsePrefixExpression();
        $this->prefixFns[TokenType::MINUS->value]    = fn() => $this->parsePrefixExpression();
        $this->prefixFns[TokenType::LPAREN->value]   = fn() => $this->parseGroupedExpression();
        $this->prefixFns[TokenType::IF->value]       = fn() => $this->parseIfExpression();
        $this->prefixFns[TokenType::WHILE->value]    = fn() => $this->parseWhileExpression();
        $this->prefixFns[TokenType::FOR->value]      = fn() => $this->parseForInExpression();
        $this->prefixFns[TokenType::FUNCTION->value] = fn() => $this->parseFunctionLiteral();
        $this->prefixFns[TokenType::LBRACKET->value] = fn() => $this->parseArrayLiteral();
        $this->prefixFns[TokenType::LBRACE->value]   = fn() => $this->parseHashLiteral();
    }

    private function registerInfixes(): void {
        $infixTokens = [
            TokenType::PLUS, TokenType::MINUS, TokenType::ASTERISK,
            TokenType::SLASH, TokenType::PERCENT,
            TokenType::EQ, TokenType::NOT_EQ,
            TokenType::LT, TokenType::GT, TokenType::LT_EQ, TokenType::GT_EQ,
            TokenType::AND, TokenType::OR,
        ];
        foreach ($infixTokens as $t) {
            $this->infixFns[$t->value] = fn(Expression $left) => $this->parseInfixExpression($left);
        }
        $this->infixFns[TokenType::LPAREN->value]   = fn(Expression $fn)   => $this->parseCallExpression($fn);
        $this->infixFns[TokenType::LBRACKET->value] = fn(Expression $left) => $this->parseIndexExpression($left);
        $this->infixFns[TokenType::DOT->value]      = fn(Expression $left) => $this->parseDotAccess($left);
    }

    public function parseProgram(): Program {
        $program = new Program();
        while ($this->stream->cur()->type !== TokenType::EOF) {
            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $program->statements[] = $stmt;
            }
            $this->stream->next();
        }
        return $program;
    }

    public function errors(): array { return $this->errors; }

    private function parseStatement(): ?Statement {
        return match($this->stream->cur()->type) {
            TokenType::LET    => $this->parseLetStatement(),
            TokenType::RETURN => $this->parseReturnStatement(),
            TokenType::IMPORT => $this->parseImportStatement(),
            default           => $this->parseExpressionStatementOrAssign(),
        };
    }

    private function parseLetStatement(): ?LetStatement {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $name = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        if (!$this->expectPeek(TokenType::ASSIGN)) return null;
        $this->stream->next();
        $value = $this->parseExpression(Precedence::LOWEST);
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new LetStatement($tok, $name, $value);
    }

    private function parseReturnStatement(): ?ReturnStatement {
        $tok = $this->stream->cur();
        $this->stream->next();
        $value = $this->parseExpression(Precedence::LOWEST);
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new ReturnStatement($tok, $value);
    }

    private function parseExpressionStatementOrAssign(): ?Statement {
        // check for bare assignment: IDENT = expr (no let)
        if ($this->stream->cur()->type === TokenType::IDENT && $this->stream->peekIs(TokenType::ASSIGN)) {
            return $this->parseAssignStatement();
        }
        $tok  = $this->stream->cur();
        $expr = $this->parseExpression(Precedence::LOWEST);
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new ExpressionStatement($tok, $expr);
    }

    private function parseAssignStatement(): ?AssignStatement {
        $tok  = $this->stream->cur();
        $name = new Identifier($tok, $tok->literal);
        $this->stream->next(); // consume IDENT
        $this->stream->next(); // consume =
        $value = $this->parseExpression(Precedence::LOWEST);
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new AssignStatement($tok, $name, $value);
    }

    private function parseExpression(int $priority): ?Expression {
        $prefix = $this->prefixFns[$this->stream->cur()->type->value] ?? null;
        if ($prefix === null) {
            $this->errors[] = "no prefix parse function for {$this->stream->cur()->type->value} found (line {$this->stream->cur()->line})";
            return null;
        }
        $left = $prefix();
        while (!$this->stream->peekIs(TokenType::SEMICOLON) && $priority < $this->peekPriority()) {
            $infix = $this->infixFns[$this->stream->peek()->type->value] ?? null;
            if ($infix === null) return $left;
            $this->stream->next();
            $left = $infix($left);
        }
        return $left;
    }

    private function parseIntegerLiteral(): ?IntegerLiteral {
        $tok = $this->stream->cur();
        if (!is_numeric($tok->literal)) {
            $this->errors[] = "could not parse {$tok->literal} as integer";
            return null;
        }
        return new IntegerLiteral($tok, (int)$tok->literal);
    }

    private function parseFloatLiteral(): ?FloatLiteral {
        $tok = $this->stream->cur();
        if (!is_numeric($tok->literal)) {
            $this->errors[] = "could not parse {$tok->literal} as float";
            return null;
        }
        return new FloatLiteral($tok, (float)$tok->literal);
    }

    private function parsePrefixExpression(): PrefixExpression {
        $tok = $this->stream->cur();
        $op  = $tok->literal;
        $this->stream->next();
        return new PrefixExpression($tok, $op, $this->parseExpression(Precedence::PREFIX));
    }

    private function parseInfixExpression(Expression $left): InfixExpression {
        $tok      = $this->stream->cur();
        $op       = $tok->literal;
        $priority = $this->curPriority();
        $this->stream->next();
        return new InfixExpression($tok, $left, $op, $this->parseExpression($priority));
    }

    private function parseGroupedExpression(): ?Expression {
        $this->stream->next();
        $exp = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        return $exp;
    }

    private function parseBlockStatement(): BlockStatement {
        $tok   = $this->stream->cur();
        $block = new BlockStatement($tok);
        $this->stream->next();
        while (!$this->stream->curIs(TokenType::RBRACE) && !$this->stream->curIs(TokenType::EOF)) {
            $stmt = $this->parseStatement();
            if ($stmt !== null) $block->statements[] = $stmt;
            $this->stream->next();
        }
        return $block;
    }

    private function parseIfExpression(): ?IfExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        $this->stream->next();
        $condition = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $consequence = $this->parseBlockStatement();
        $alternative = null;
        if ($this->stream->peekIs(TokenType::ELSE)) {
            $this->stream->next();
            if (!$this->expectPeek(TokenType::LBRACE)) return null;
            $alternative = $this->parseBlockStatement();
        }
        return new IfExpression($tok, $condition, $consequence, $alternative);
    }

    private function parseWhileExpression(): ?WhileExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        $this->stream->next();
        $condition = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $body = $this->parseBlockStatement();
        return new WhileExpression($tok, $condition, $body);
    }

    private function parseForInExpression(): ?ForInExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $variable = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        if (!$this->expectPeek(TokenType::IN)) return null;
        $this->stream->next();
        $iterable = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $body = $this->parseBlockStatement();
        return new ForInExpression($tok, $variable, $iterable, $body);
    }

    private function parseFunctionLiteral(): ?FunctionLiteral {
        $tok    = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        $params = $this->parseFunctionParameters();
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $body = $this->parseBlockStatement();
        return new FunctionLiteral($tok, $params, $body);
    }

    /** @return Identifier[] */
    private function parseFunctionParameters(): array {
        $params = [];
        if ($this->stream->peekIs(TokenType::RPAREN)) {
            $this->stream->next();
            return $params;
        }
        $this->stream->next();
        $params[] = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        while ($this->stream->peekIs(TokenType::COMMA)) {
            $this->stream->next();
            $this->stream->next();
            $params[] = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        }
        if (!$this->expectPeek(TokenType::RPAREN)) return [];
        return $params;
    }

    private function parseCallExpression(Expression $fn): CallExpression {
        $tok  = $this->stream->cur();
        $args = $this->parseExpressionList(TokenType::RPAREN);
        return new CallExpression($tok, $fn, $args);
    }

    private function parseArrayLiteral(): ArrayLiteral {
        $tok      = $this->stream->cur();
        $elements = $this->parseExpressionList(TokenType::RBRACKET);
        return new ArrayLiteral($tok, $elements);
    }

    /** @return Expression[] */
    private function parseExpressionList(TokenType $end): array {
        $list = [];
        if ($this->stream->peekIs($end)) {
            $this->stream->next();
            return $list;
        }
        $this->stream->next();
        $list[] = $this->parseExpression(Precedence::LOWEST);
        while ($this->stream->peekIs(TokenType::COMMA)) {
            $this->stream->next();
            $this->stream->next();
            $list[] = $this->parseExpression(Precedence::LOWEST);
        }
        if (!$this->expectPeek($end)) return [];
        return $list;
    }

    private function parseIndexExpression(Expression $left): ?IndexExpression {
        $tok = $this->stream->cur();
        $this->stream->next();
        $index = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RBRACKET)) return null;
        return new IndexExpression($tok, $left, $index);
    }

    private function parseHashLiteral(): ?HashLiteral {
        $tok   = $this->stream->cur();
        $pairs = [];
        while (!$this->stream->peekIs(TokenType::RBRACE)) {
            $this->stream->next();
            $key = $this->parseExpression(Precedence::LOWEST);
            if (!$this->expectPeek(TokenType::COLON)) return null;
            $this->stream->next();
            $value = $this->parseExpression(Precedence::LOWEST);
            $pairs[] = [$key, $value];
            if (!$this->stream->peekIs(TokenType::RBRACE) && !$this->expectPeek(TokenType::COMMA)) return null;
        }
        if (!$this->expectPeek(TokenType::RBRACE)) return null;
        return new HashLiteral($tok, $pairs);
    }

    private function parseDotAccess(Expression $left): ?IndexExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $key = new StringLiteral($this->stream->cur(), $this->stream->cur()->literal);
        return new IndexExpression($tok, $left, $key);
    }

    private function parseImportStatement(): ?ImportStatement {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::STRING)) return null;
        $path = $this->stream->cur()->literal;
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new ImportStatement($tok, $path);
    }

    private function expectPeek(TokenType $t): bool {
        if ($this->stream->peekIs($t)) {
            $this->stream->next();
            return true;
        }
        $this->errors[] = "expected {$t->value}, got {$this->stream->peek()->type->value} (line {$this->stream->peek()->line})";
        return false;
    }

    private function peekPriority(): int { return $this->precedence->of($this->stream->peek()->type); }
    private function curPriority(): int  { return $this->precedence->of($this->stream->cur()->type); }
}
