<?php
declare(strict_types=1);
namespace Fel\Parser;

use Fel\Ast\Node\{
    Program, LetStatement, AssignStatement, ReturnStatement,
    ExpressionStatement, BlockStatement, BreakStatement, ContinueStatement,
    Identifier, IntegerLiteral, FloatLiteral, StringLiteral, BooleanLiteral, NullLiteral,
    PrefixExpression, InfixExpression,
    IfExpression, WhileExpression, ForInExpression,
    FunctionLiteral, CallExpression,
    ArrayLiteral, IndexExpression, HashLiteral,
    ImportStatement,
    TryExpression, ThrowStatement, MatchExpression,
    StructDefinition, InterfaceDefinition, MethodDefinition, StructLiteral,
    YieldExpression,
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
        $this->prefixFns[TokenType::TRY->value]      = fn() => $this->parseTryExpression();
        $this->prefixFns[TokenType::MATCH->value]    = fn() => $this->parseMatchExpression();
        $this->prefixFns[TokenType::YIELD->value]    = fn() => $this->parseYieldExpression();
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
        $this->infixFns[TokenType::LBRACE->value]   = fn(Expression $left) => $this->parseStructLiteral($left);
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
        // method definition: `fn (recv Type) name(...) {...}` (two idents inside first paren)
        if ($this->stream->curIs(TokenType::FUNCTION)
            && $this->stream->peekIs(TokenType::LPAREN)
            && $this->stream->peekAt(2)->type === TokenType::IDENT
            && $this->stream->peekAt(3)->type === TokenType::IDENT) {
            return $this->parseMethodDefinition();
        }
        return match($this->stream->cur()->type) {
            TokenType::LET       => $this->parseLetStatement(),
            TokenType::RETURN    => $this->parseReturnStatement(),
            TokenType::IMPORT    => $this->parseImportStatement(),
            TokenType::BREAK     => $this->parseBreakStatement(),
            TokenType::CONTINUE  => $this->parseContinueStatement(),
            TokenType::THROW     => $this->parseThrowStatement(),
            TokenType::STRUCT    => $this->parseStructDefinition(),
            TokenType::INTERFACE => $this->parseInterfaceDefinition(),
            default              => $this->parseExpressionStatementOrAssign(),
        };
    }

    private function parseLetStatement(): ?LetStatement {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $name = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        $this->skipOptionalType();
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

    private function parseBreakStatement(): BreakStatement {
        $tok = $this->stream->cur();
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new BreakStatement($tok);
    }

    private function parseContinueStatement(): ContinueStatement {
        $tok = $this->stream->cur();
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new ContinueStatement($tok);
    }

    private const COMPOUND_OPS = [
        'PLUS_ASSIGN'     => '+',
        'MINUS_ASSIGN'    => '-',
        'ASTERISK_ASSIGN' => '*',
        'SLASH_ASSIGN'    => '/',
        'PERCENT_ASSIGN'  => '%',
    ];

    private function parseStructDefinition(): ?StructDefinition {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $name = $this->stream->cur()->literal;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $fields = $this->parseIdentList();
        if (!$this->expectPeek(TokenType::RBRACE)) return null;
        return new StructDefinition($tok, $name, $fields);
    }

    private function parseInterfaceDefinition(): ?InterfaceDefinition {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $name = $this->stream->cur()->literal;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $methods = $this->parseIdentList();
        if (!$this->expectPeek(TokenType::RBRACE)) return null;
        return new InterfaceDefinition($tok, $name, $methods);
    }

    /** Parse a comma-separated list of bare identifiers up to (not including) the closing brace. */
    private function parseIdentList(): array {
        $names = [];
        if ($this->stream->peekIs(TokenType::RBRACE)) return $names;
        if (!$this->expectPeek(TokenType::IDENT)) return $names;
        $names[] = $this->stream->cur()->literal;
        $this->skipOptionalType();
        while ($this->stream->peekIs(TokenType::COMMA)) {
            $this->stream->next();
            $this->stream->next();
            $names[] = $this->stream->cur()->literal;
            $this->skipOptionalType();
        }
        return $names;
    }

    private function parseMethodDefinition(): ?MethodDefinition {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $recvVar = $this->stream->cur()->literal;
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $recvType = $this->stream->cur()->literal;
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $name = $this->stream->cur()->literal;
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        $params = $this->parseFunctionParameters();
        $this->skipOptionalType(); // optional return type
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $body = $this->parseBlockStatement();
        return new MethodDefinition($tok, $recvVar, $recvType, $name, $params, $body);
    }

    private function parseStructLiteral(Expression $left): ?StructLiteral {
        if (!$left instanceof Identifier) {
            $this->errors[] = "struct literal requires a type name (line {$this->stream->cur()->line})";
            return null;
        }
        $tok    = $this->stream->cur(); // LBRACE
        $fields = [];
        while (!$this->stream->peekIs(TokenType::RBRACE)) {
            if (!$this->expectPeek(TokenType::IDENT)) return null;
            $fieldName = $this->stream->cur()->literal;
            if (!$this->expectPeek(TokenType::COLON)) return null;
            $this->stream->next();
            $fields[$fieldName] = $this->parseExpression(Precedence::LOWEST);
            if (!$this->stream->peekIs(TokenType::RBRACE) && !$this->expectPeek(TokenType::COMMA)) return null;
        }
        if (!$this->expectPeek(TokenType::RBRACE)) return null;
        return new StructLiteral($tok, $left->value, $fields);
    }

    private function parseThrowStatement(): ?ThrowStatement {
        $tok = $this->stream->cur();
        $this->stream->next();
        $value = $this->parseExpression(Precedence::LOWEST);
        if ($value === null) return null;
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        return new ThrowStatement($tok, $value);
    }

    private function parseYieldExpression(): ?YieldExpression {
        $tok = $this->stream->cur();
        $this->stream->next();
        $value = $this->parseExpression(Precedence::LOWEST);
        if ($value === null) return null;
        return new YieldExpression($tok, $value);
    }

    private function parseTryExpression(): ?TryExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $body = $this->parseBlockStatement();
        if (!$this->expectPeek(TokenType::CATCH)) return null;
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        if (!$this->expectPeek(TokenType::IDENT)) return null;
        $catchVar = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;
        $catchBody = $this->parseBlockStatement();
        return new TryExpression($tok, $body, $catchVar, $catchBody);
    }

    private function parseMatchExpression(): ?MatchExpression {
        $tok = $this->stream->cur();
        if (!$this->expectPeek(TokenType::LPAREN)) return null;
        $this->stream->next();
        $subject = $this->parseExpression(Precedence::LOWEST);
        if (!$this->expectPeek(TokenType::RPAREN)) return null;
        if (!$this->expectPeek(TokenType::LBRACE)) return null;

        $arms = [];
        while (!$this->stream->peekIs(TokenType::RBRACE)) {
            $this->stream->next();
            // wildcard `_` or a pattern expression
            if ($this->stream->curIs(TokenType::IDENT) && $this->stream->cur()->literal === '_') {
                $pattern = null;
            } else {
                $pattern = $this->parseExpression(Precedence::LOWEST);
            }
            if (!$this->expectPeek(TokenType::FAT_ARROW)) return null;
            $this->stream->next();
            $result = $this->parseExpression(Precedence::LOWEST);
            $arms[] = ['pattern' => $pattern, 'result' => $result];
            if (!$this->stream->peekIs(TokenType::RBRACE) && !$this->expectPeek(TokenType::COMMA)) return null;
        }
        if (!$this->expectPeek(TokenType::RBRACE)) return null;
        return new MatchExpression($tok, $subject, $arms);
    }

    private function parseExpressionStatementOrAssign(): ?Statement {
        // check for bare assignment: IDENT = expr (no let)
        if ($this->stream->cur()->type === TokenType::IDENT && $this->stream->peekIs(TokenType::ASSIGN)) {
            return $this->parseAssignStatement();
        }
        // compound assignment: IDENT op= expr  -->  IDENT = IDENT op expr
        if ($this->stream->cur()->type === TokenType::IDENT
            && isset(self::COMPOUND_OPS[$this->stream->peek()->type->name])) {
            return $this->parseCompoundAssignStatement();
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

    private function parseCompoundAssignStatement(): ?AssignStatement {
        $tok  = $this->stream->cur();
        $name = new Identifier($tok, $tok->literal);
        $this->stream->next();                       // consume IDENT
        $op   = self::COMPOUND_OPS[$this->stream->cur()->type->name];
        $opTok = $this->stream->cur();
        $this->stream->next();                       // consume op=
        $rhs  = $this->parseExpression(Precedence::LOWEST);
        if ($this->stream->peekIs(TokenType::SEMICOLON)) $this->stream->next();
        $combined = new InfixExpression($opTok, $name, $op, $rhs);
        return new AssignStatement($tok, $name, $combined);
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
            if ($this->stream->peekIs(TokenType::IF)) {
                // `else if` — desugar to nested if-expression wrapped in a block
                $this->stream->next();
                $nested = $this->parseIfExpression();
                if ($nested === null) return null;
                $alternative = new BlockStatement($nested->token, [
                    new ExpressionStatement($nested->token, $nested),
                ]);
            } else {
                if (!$this->expectPeek(TokenType::LBRACE)) return null;
                $alternative = $this->parseBlockStatement();
            }
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
        $this->skipOptionalType(); // optional return type
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
        $this->skipOptionalType();
        while ($this->stream->peekIs(TokenType::COMMA)) {
            $this->stream->next();
            $this->stream->next();
            $params[] = new Identifier($this->stream->cur(), $this->stream->cur()->literal);
            $this->skipOptionalType();
        }
        if (!$this->expectPeek(TokenType::RPAREN)) return [];
        return $params;
    }

    /** Optional Go-style `: Type` annotation — parsed and discarded (runtime is dynamic). */
    private function skipOptionalType(): void {
        if (!$this->stream->peekIs(TokenType::COLON)) return;
        $this->stream->next(); // consume ':'
        // array type prefix `[]`
        if ($this->stream->peekIs(TokenType::LBRACKET)) {
            $this->stream->next();
            $this->expectPeek(TokenType::RBRACKET);
        }
        $this->expectPeek(TokenType::IDENT); // type name
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
