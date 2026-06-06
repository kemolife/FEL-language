<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Statement;
use Fel\Token\Token;

final class InterfaceDefinition implements Statement {
    /** @param string[] $methods */
    public function __construct(
        public readonly Token  $token,
        public readonly string $name,
        public readonly array  $methods,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return "interface {$this->name} { " . implode(', ', $this->methods) . ' }';
    }
}
