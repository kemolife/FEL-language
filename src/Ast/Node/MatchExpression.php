<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\Expression;
use Fel\Token\Token;

final class MatchExpression implements Expression {
    /**
     * @param array<array{pattern: ?Expression, result: Expression}> $arms
     *        pattern === null means the wildcard arm (`_`).
     */
    public function __construct(
        public readonly Token      $token,
        public readonly Expression $subject,
        public readonly array      $arms,
    ) {}

    public function expressionNode(): void {}
    public function tokenLiteral(): string { return $this->token->literal; }
    public function string(): string {
        $arms = array_map(
            fn($a) => ($a['pattern']?->string() ?? '_') . ' => ' . $a['result']->string(),
            $this->arms
        );
        return "match ({$this->subject->string()}) { " . implode(', ', $arms) . ' }';
    }
}
