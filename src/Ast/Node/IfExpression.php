<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class IfExpression implements Expression {
    public function __construct(
        public readonly Token           $token,
        public readonly Expression      $condition,
        public readonly BlockStatement  $consequence,
        public readonly ?BlockStatement $alternative,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $out = "if{$this->condition->string()} {$this->consequence->string()}";
        if ($this->alternative !== null) {
            $out .= " else {$this->alternative->string()}";
        }
        return $out;
    }
}
