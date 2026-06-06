<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class ForInExpression implements Expression {
    public function __construct(
        public readonly Token          $token,
        public readonly Identifier     $variable,
        public readonly Expression     $iterable,
        public readonly BlockStatement $body,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return "for ({$this->variable->string()} in {$this->iterable->string()}) {$this->body->string()}";
    }
}
