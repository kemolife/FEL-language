<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Statement;
use Fel\Token\Token;

final class ContinueStatement implements Statement {
    public function __construct(public readonly Token $token) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string { return 'continue;'; }
}
