<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class WhileExpression implements Expression {
    public function __construct(
        public readonly Token          $token,
        public readonly Expression     $condition,
        public readonly BlockStatement $body,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return "while{$this->condition->string()} {$this->body->string()}";
    }
}
