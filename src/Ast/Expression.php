<?php
declare(strict_types=1);
namespace Fel\Ast;

interface Expression extends Node {
    public function expressionNode(): void;
}
