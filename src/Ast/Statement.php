<?php
declare(strict_types=1);
namespace Fel\Ast;

interface Statement extends Node {
    public function statementNode(): void;
}
