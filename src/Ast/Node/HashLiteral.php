<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class HashLiteral implements Expression {
    /** @param array<array{0: Expression, 1: Expression}> $pairs list of [keyExpr, valueExpr] tuples */
    public function __construct(
        public readonly Token $token,
        public readonly array $pairs,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $parts = [];
        foreach ($this->pairs as [$key, $value]) {
            $parts[] = $key->string() . ':' . $value->string();
        }
        return '{' . implode(', ', $parts) . '}';
    }
}
