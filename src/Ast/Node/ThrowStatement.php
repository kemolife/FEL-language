<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\{Statement, Expression};
use Fel\Token\Token;

final class ThrowStatement implements Statement {
    public function __construct(
        public readonly Token      $token,
        public readonly Expression $value,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string { return "throw {$this->value->string()};"; }
}
