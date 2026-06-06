<?php
declare(strict_types=1);
namespace Fel\Ast;

interface Node {
    public function tokenLiteral(): string;
    public function string(): string;
}
