<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class ArrayLiteral implements Expression {
    /** @param Expression[] $elements */
    public function __construct(
        public readonly Token $token,
        public readonly array $elements,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $els = implode(', ', array_map(fn($e) => $e->string(), $this->elements));
        return "[{$els}]";
    }
}
