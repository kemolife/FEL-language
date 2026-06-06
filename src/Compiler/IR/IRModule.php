<?php
declare(strict_types=1);
namespace Fel\Compiler\IR;

/**
 * Top-level LLVM IR module. Collects type definitions, globals,
 * function declarations, and function definitions, then emits
 * a complete .ll file as a string.
 */
class IRModule {
    private array $typeDecls    = [];
    private array $globals      = [];
    private array $declarations = [];
    private array $definitions  = [];
    private int   $globalCount  = 0;

    public function __construct(public readonly string $name = 'fel_module') {
        $this->addRuntimeTypeDecls();
        $this->addRuntimeDeclarations();
    }

    // ── Type declarations ───────────────────────────────────────────────────

    private function addRuntimeTypeDecls(): void {
        // Tagged dynamic value: { tag(i8), gc_marked(i8), padding(i8[6]), payload(i64), gc_next(ptr) }
        $this->typeDecls[] = '%FelVal = type { i8, i8, [6 x i8], i64, ptr }';
        // String: { len(i64), data(ptr) }
        $this->typeDecls[] = '%FelStr = type { i64, ptr }';
        // Array: { len(i64), cap(i64), data(ptr) }
        $this->typeDecls[] = '%FelArr = type { i64, i64, ptr }';
    }

    // ── External runtime declarations ───────────────────────────────────────

    private function addRuntimeDeclarations(): void {
        $this->declarations[] = 'declare ptr  @fel_alloc(i64)';
        $this->declarations[] = 'declare void @fel_free(ptr)';
        $this->declarations[] = 'declare ptr  @fel_str_new(ptr, i64)';
        $this->declarations[] = 'declare ptr  @fel_arr_new(i64)';
        $this->declarations[] = 'declare void @fel_arr_push(ptr, ptr)';
        $this->declarations[] = 'declare ptr  @fel_val_int(i64)';
        $this->declarations[] = 'declare ptr  @fel_val_float(double)';
        $this->declarations[] = 'declare ptr  @fel_val_bool(i8)';
        $this->declarations[] = 'declare ptr  @fel_val_null()';
        $this->declarations[] = 'declare void @fel_display(ptr)';
        $this->declarations[] = 'declare i8   @fel_is_truthy(ptr)';
        $this->declarations[] = 'declare ptr  @fel_binop(i8, ptr, ptr)';
        $this->declarations[] = 'declare ptr  @fel_unop(i8, ptr)';
        $this->declarations[] = 'declare void @fel_gc_init()';
        $this->declarations[] = 'declare void @fel_gc_shutdown()';
        $this->declarations[] = 'declare void @fel_gc_collect()';
        $this->declarations[] = 'declare void @fel_gc_push_root(ptr)';
        $this->declarations[] = 'declare void @fel_gc_pop_roots(i32)';
        $this->declarations[] = 'declare i32  @printf(ptr, ...)';
        $this->declarations[] = 'declare ptr  @malloc(i64)';
        $this->declarations[] = 'declare void @free(ptr)';
    }

    // ── Global strings ──────────────────────────────────────────────────────

    public function addGlobalString(string $value): string {
        $name    = '@.str.' . $this->globalCount++;
        $escaped = $this->escapeString($value);
        $len     = strlen($value) + 1;
        $this->globals[] = "{$name} = private unnamed_addr constant [{$len} x i8] c\"{$escaped}\\00\"";
        return $name;
    }

    // ── Function definitions ────────────────────────────────────────────────

    public function addFunction(IRFunction $fn): void {
        $this->definitions[] = $fn;
    }

    // ── Emit ────────────────────────────────────────────────────────────────

    public function emit(): string {
        $lines = [];
        $lines[] = "; ModuleID = '{$this->name}'";
        $lines[] = 'source_filename = "' . $this->name . '.fel"';
        $lines[] = 'target datalayout = "e-m:o-p270:32:32-p271:32:32-p272:64:64-i64:64-f80:128-n8:16:32:64-S128"';
        $lines[] = '';

        if ($this->typeDecls) {
            $lines = array_merge($lines, $this->typeDecls);
            $lines[] = '';
        }

        if ($this->globals) {
            $lines = array_merge($lines, $this->globals);
            $lines[] = '';
        }

        if ($this->declarations) {
            $lines = array_merge($lines, $this->declarations);
            $lines[] = '';
        }

        foreach ($this->definitions as $fn) {
            $lines[] = $fn->emit();
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function escapeString(string $s): string {
        $result = '';
        for ($i = 0; $i < strlen($s); $i++) {
            $b = ord($s[$i]);
            $result .= $b < 32 || $b > 126 || $b === ord('\\') || $b === ord('"')
                ? sprintf('\\%02X', $b)
                : $s[$i];
        }
        return $result;
    }
}
