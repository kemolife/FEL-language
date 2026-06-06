<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Statement;
use Fel\Token\Token;

final class StructDefinition implements Statement {
    /** @param string[] $fields */
    public function __construct(
        public readonly Token  $token,
        public readonly string $name,
        public readonly array  $fields,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        return "struct {$this->name} { " . implode(', ', $this->fields) . ' }';
    }
}
