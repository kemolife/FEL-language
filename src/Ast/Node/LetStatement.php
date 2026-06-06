<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\{Statement, Expression};
use Fel\Token\Token;

final class LetStatement implements Statement {
    public function __construct(
        public readonly Token       $token,
        public readonly Identifier  $name,
        public readonly ?Expression $value,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $val = $this->value?->string() ?? '';
        return "let {$this->name->string()} = {$val};";
    }
}
