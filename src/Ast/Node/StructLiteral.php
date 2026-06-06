<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

/** A struct composite literal: `Point{x: 1, y: 2}`. */
final class StructLiteral implements Expression {
    /** @param array<string, Expression> $fields field name => value expression */
    public function __construct(
        public readonly Token  $token,
        public readonly string $typeName,
        public readonly array  $fields,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $parts = [];
        foreach ($this->fields as $name => $expr) {
            $parts[] = "{$name}: {$expr->string()}";
        }
        return "{$this->typeName}{" . implode(', ', $parts) . '}';
    }
}
