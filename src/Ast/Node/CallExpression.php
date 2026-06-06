<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class CallExpression implements Expression {
    /** @param Expression[] $arguments */
    public function __construct(
        public readonly Token      $token,
        public readonly Expression $function,
        public readonly array      $arguments,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $args = implode(', ', array_map(fn($a) => $a->string(), $this->arguments));
        return "{$this->function->string()}({$args})";
    }
}
