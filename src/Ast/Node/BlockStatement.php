<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\{Statement};
use Fel\Token\Token;

final class BlockStatement implements Statement {
    /** @param Statement[] $statements */
    public function __construct(
        public readonly Token $token,
        public array          $statements = [],
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return implode('', array_map(fn($s) => $s->string(), $this->statements));
    }
}
