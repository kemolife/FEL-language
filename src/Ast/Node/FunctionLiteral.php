<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class FunctionLiteral implements Expression {
    /** @param Identifier[] $parameters */
    public function __construct(
        public readonly Token          $token,
        public readonly array          $parameters,
        public readonly BlockStatement $body,
        public readonly ?string        $name = null,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $params = implode(', ', array_map(fn($p) => $p->string(), $this->parameters));
        $name   = $this->name ? "<{$this->name}>" : '';
        return "fn{$name}({$params}) {$this->body->string()}";
    }
}
