<?php
declare(strict_types=1);
namespace Fel\Compiler;

use Fel\Compiler\IR\IRValue;

final class SymbolTable {
    private array $scopes = [[]]; // stack of [name => IRValue]

    public function push(): void {
        $this->scopes[] = [];
    }

    public function pop(): void {
        array_pop($this->scopes);
    }

    public function define(string $name, IRValue $val): void {
        $this->scopes[count($this->scopes) - 1][$name] = $val;
    }

    public function resolve(string $name): ?IRValue {
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i][$name])) {
                return $this->scopes[$i][$name];
            }
        }
        return null;
    }
}
