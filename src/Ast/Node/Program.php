<?php
declare(strict_types=1);
namespace Fel\Ast\Node;
use Fel\Ast\{Node, Statement};

class Program implements Node {
    /** @param Statement[] $statements */
    public function __construct(public array $statements = []) {}

    public function tokenLiteral(): string {
        return $this->statements[0]?->tokenLiteral() ?? '';
    }

    public function string(): string {
        return implode('', array_map(fn($s) => $s->string(), $this->statements));
    }
}
