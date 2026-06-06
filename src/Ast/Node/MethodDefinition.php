<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Statement;
use Fel\Token\Token;

/** A method attached to a struct type: `fn (p Point) dist() { ... }`. */
final class MethodDefinition implements Statement {
    /** @param Identifier[] $parameters */
    public function __construct(
        public readonly Token          $token,
        public readonly string         $receiverVar,
        public readonly string         $receiverType,
        public readonly string         $name,
        public readonly array          $parameters,
        public readonly BlockStatement $body,
    ) {}

    public function statementNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $params = implode(', ', array_map(fn($p) => $p->string(), $this->parameters));
        return "fn ({$this->receiverVar} {$this->receiverType}) {$this->name}({$params}) {$this->body->string()}";
    }
}
