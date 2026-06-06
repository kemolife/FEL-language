<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class IntegerLiteral implements Expression {
    public function __construct(
        public readonly Token $token,
        public readonly int   $value,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string { return $this->token->literal; }
}
