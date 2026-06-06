<?php
declare(strict_types=1);
namespace Fel\Compiler\IR;

/**
 * Builds LLVM IR instructions as strings.
 * Each method returns the IR text line(s) and the result IRValue (if any).
 */
class IRBuilder {
    private array  $instructions = [];
    private string $currentBlock = 'entry';

    public function block(string $label): void {
        $this->currentBlock = $label;
        $this->instructions[] = "{$label}:";
    }

    public function alloca(IRType $type, string $name): IRValue {
        $val = new IRValue($type, '%' . $name);
        $this->emit("{$val->ref()} = alloca {$type->value}");
        return $val;
    }

    public function store(IRValue $val, IRValue $ptr): void {
        $this->emit("store {$val->type->value} {$val->ref()}, {$ptr->type->value} {$ptr->ref()}");
    }

    public function load(IRValue $ptr): IRValue {
        $result = new IRValue($ptr->type);
        $this->emit("{$result->ref()} = load {$ptr->type->value}, {$ptr->type->value} {$ptr->ref()}");
        return $result;
    }

    public function trunc(IRValue $val, IRType $toType): IRValue {
        $result = new IRValue($toType);
        $this->emit("{$result->ref()} = trunc {$val->type->value} {$val->ref()} to {$toType->value}");
        return $result;
    }

    public function add(IRValue $l, IRValue $r): IRValue {
        return $this->binop('add', $l, $r);
    }

    public function sub(IRValue $l, IRValue $r): IRValue {
        return $this->binop('sub', $l, $r);
    }

    public function mul(IRValue $l, IRValue $r): IRValue {
        return $this->binop('mul', $l, $r);
    }

    public function sdiv(IRValue $l, IRValue $r): IRValue {
        return $this->binop('sdiv', $l, $r);
    }

    public function srem(IRValue $l, IRValue $r): IRValue {
        return $this->binop('srem', $l, $r);
    }

    public function fadd(IRValue $l, IRValue $r): IRValue {
        return $this->binop('fadd', $l, $r);
    }

    public function fdiv(IRValue $l, IRValue $r): IRValue {
        return $this->binop('fdiv', $l, $r);
    }

    public function icmp(string $pred, IRValue $l, IRValue $r): IRValue {
        $result = new IRValue(IRType::I1);
        $this->emit("{$result->ref()} = icmp {$pred} {$l->type->value} {$l->ref()}, {$r->ref()}");
        return $result;
    }

    public function fcmp(string $pred, IRValue $l, IRValue $r): IRValue {
        $result = new IRValue(IRType::I1);
        $this->emit("{$result->ref()} = fcmp {$pred} {$l->type->value} {$l->ref()}, {$r->ref()}");
        return $result;
    }

    public function br(IRValue $cond, string $trueLabel, string $falseLabel): void {
        $this->emit("br i1 {$cond->ref()}, label %{$trueLabel}, label %{$falseLabel}");
    }

    public function jump(string $label): void {
        $this->emit("br label %{$label}");
    }

    public function ret(IRValue $val): void {
        $this->emit("ret {$val->type->value} {$val->ref()}");
    }

    public function retVoid(): void {
        $this->emit('ret void');
    }

    public function call(string $fn, IRType $returnType, array $args): ?IRValue {
        $argStr = implode(', ', array_map(fn($a) => "{$a->type->value} {$a->ref()}", $args));
        if ($returnType === IRType::VOID) {
            $this->emit("call {$returnType->value} @{$fn}({$argStr})");
            return null;
        }
        $result = new IRValue($returnType);
        $this->emit("{$result->ref()} = call {$returnType->value} @{$fn}({$argStr})");
        return $result;
    }

    public function constInt(IRType $type, int $value): IRValue {
        // inline constant — no instruction needed, just a reference
        return new IRValue($type, (string)$value);
    }

    public function constFloat(float $value): IRValue {
        // LLVM requires float literals to either carry a decimal point or be
        // IEEE-754 hex. A value like 2.0 formatted as "2" is rejected. Emit the
        // exact 64-bit bit pattern as hex (0x...), which LLVM always accepts.
        $bits = unpack('Q', pack('d', $value))[1];
        return new IRValue(IRType::F64, sprintf('0x%016X', $bits));
    }

    public function phi(IRType $type, array $incoming): IRValue {
        $result  = new IRValue($type);
        $entries = implode(', ', array_map(fn($i) => "[ {$i[0]->ref()}, %{$i[1]} ]", $incoming));
        $this->emit("{$result->ref()} = phi {$type->value} {$entries}");
        return $result;
    }

    public function instructions(): array { return $this->instructions; }

    private function binop(string $op, IRValue $l, IRValue $r): IRValue {
        $result = new IRValue($l->type);
        $this->emit("{$result->ref()} = {$op} {$l->type->value} {$l->ref()}, {$r->ref()}");
        return $result;
    }

    private function emit(string $instr): void {
        $this->instructions[] = '  ' . $instr;
    }
}
