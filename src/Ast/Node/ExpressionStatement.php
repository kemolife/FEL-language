<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\{Statement, Expression};
use Fel\Token\Token;

final class ExpressionStatement implements Statement {
    public function __construct(
        public readonly Token       $token,
        public readonly ?Expression $expression,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string { return $this->expression?->string() ?? ''; }
}
