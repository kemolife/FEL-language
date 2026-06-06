<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class TryExpression implements Expression {
    public function __construct(
        public readonly Token          $token,
        public readonly BlockStatement $body,
        public readonly Identifier     $catchVar,
        public readonly BlockStatement $catchBody,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return "try {$this->body->string()} catch ({$this->catchVar->string()}) {$this->catchBody->string()}";
    }
}
