<?php
declare(strict_types=1);
namespace Fel\Compiler;

use Fel\Ast\Node;
use Fel\Ast\Node\{
    Program, ExpressionStatement, LetStatement, AssignStatement, ReturnStatement, BlockStatement,
    IntegerLiteral, FloatLiteral, StringLiteral, BooleanLiteral,
    Identifier, PrefixExpression, InfixExpression,
    IfExpression, WhileExpression,
    FunctionLiteral, CallExpression,
    ArrayLiteral, HashLiteral, IndexExpression,
};
use Fel\Compiler\IR\{IRBuilder, IRFunction, IRModule, IRType, IRValue};

class CodeGen {
    private SymbolTable $symbols;
    private IRModule $module;
    private IRFunction $currentFn;
    private IRBuilder $builder;
    private int $labelCount = 0;
    private int $rootCount  = 0;

    public function __construct(private readonly string $moduleName = 'fel_output') {
        $this->symbols = new SymbolTable();
        $this->module  = new IRModule($this->moduleName);
    }

    public function compile(Program $program): IRModule {
        IRValue::reset();

        // Create @fel_main() function
        $mainFn = new IRFunction('fel_main', IRType::I32);
        $this->module->addFunction($mainFn);
        $this->currentFn = $mainFn;
        $this->builder   = $mainFn->builder();

        foreach ($program->statements as $stmt) {
            $this->compileNode($stmt);
        }

        // Pop all GC roots registered in top-level scope
        if ($this->rootCount > 0) {
            $n = $this->builder->constInt(IRType::I32, $this->rootCount);
            $this->builder->call('fel_gc_pop_roots', IRType::VOID, [$n]);
        }

        // Return 0
        $zero = $this->builder->constInt(IRType::I32, 0);
        $this->builder->ret($zero);

        // Create @main() wrapper
        $this->addMainWrapper();

        return $this->module;
    }

    private function compileNode(Node $node): ?IRValue {
        return match(true) {
            $node instanceof ExpressionStatement => $this->compileNode($node->expression),
            $node instanceof LetStatement        => $this->compileLetStatement($node),
            $node instanceof AssignStatement     => $this->compileAssignStatement($node),
            $node instanceof ReturnStatement     => $this->compileReturnStatement($node),
            $node instanceof BlockStatement      => $this->compileBlock($node),
            $node instanceof IntegerLiteral      => $this->compileInteger($node),
            $node instanceof FloatLiteral        => $this->compileFloat($node),
            $node instanceof StringLiteral       => $this->compileString($node),
            $node instanceof BooleanLiteral      => $this->compileBool($node),
            $node instanceof Identifier          => $this->compileIdentifier($node),
            $node instanceof InfixExpression     => $this->compileInfix($node),
            $node instanceof PrefixExpression    => $this->compilePrefix($node),
            $node instanceof IfExpression        => $this->compileIf($node),
            $node instanceof WhileExpression     => $this->compileWhile($node),
            $node instanceof CallExpression      => $this->compileCall($node),
            $node instanceof ArrayLiteral        => $this->compileArray($node),
            default                              => null,
        };
    }

    private function compileLetStatement(LetStatement $node): ?IRValue {
        $val = $node->value ? $this->compileNode($node->value) : null;
        if (!$val) {
            // Assign null if no value provided
            $val = $this->builder->call('fel_val_null', IRType::PTR, []);
        }
        // alloca ptr for the variable slot (unique name avoids SSA collisions in nested scopes)
        $slotName = 'var_' . $node->name->value . '_' . $this->labelCount++;
        $ptr = $this->builder->alloca(IRType::PTR, $slotName);
        $this->builder->store($val, $ptr);
        // Register as GC root so mark phase can find this variable
        $this->builder->call('fel_gc_push_root', IRType::VOID, [$ptr]);
        $this->symbols->define($node->name->value, $ptr);
        $this->rootCount++;   // track roots pushed in current scope
        return null;
    }

    private function compileAssignStatement(AssignStatement $node): ?IRValue {
        $val = $this->compileNode($node->value);
        if (!$val) return null;
        $ptr = $this->symbols->resolve($node->name->value);
        if ($ptr) {
            // Re-assign existing variable
            $this->builder->store($val, $ptr);
        } else {
            // Auto-declare: alloca + store
            $ptr = $this->builder->alloca(IRType::PTR, $node->name->value);
            $this->builder->store($val, $ptr);
            $this->symbols->define($node->name->value, $ptr);
            // Register as GC root so the mark phase can find this variable
            $this->builder->call('fel_gc_push_root', IRType::VOID, [$ptr]);
            $this->rootCount++;
        }
        return null;
    }

    private function compileReturnStatement(ReturnStatement $node): ?IRValue {
        if ($node->returnValue) {
            $this->compileNode($node->returnValue); // compile for side effects
        }
        if ($this->rootCount > 0) {
            $n = $this->builder->constInt(IRType::I32, $this->rootCount);
            $this->builder->call('fel_gc_pop_roots', IRType::VOID, [$n]);
        }
        $zero = $this->builder->constInt(IRType::I32, 0);
        $this->builder->ret($zero);
        return null;
    }

    private function compileBlock(BlockStatement $node): ?IRValue {
        $rootsBefore = $this->rootCount;
        $last = null;
        $this->symbols->push();
        foreach ($node->statements as $stmt) {
            $last = $this->compileNode($stmt);
        }
        $this->symbols->pop();
        $rootsAdded = $this->rootCount - $rootsBefore;
        if ($rootsAdded > 0) {
            $n = $this->builder->constInt(IRType::I32, $rootsAdded);
            $this->builder->call('fel_gc_pop_roots', IRType::VOID, [$n]);
            $this->rootCount = $rootsBefore;
        }
        return $last;
    }

    private function compileInteger(IntegerLiteral $node): IRValue {
        $c = $this->builder->constInt(IRType::I64, $node->value);
        return $this->builder->call('fel_val_int', IRType::PTR, [$c]);
    }

    private function compileFloat(FloatLiteral $node): IRValue {
        $c = $this->builder->constFloat($node->value);
        return $this->builder->call('fel_val_float', IRType::PTR, [$c]);
    }

    private function compileString(StringLiteral $node): IRValue {
        $global = $this->module->addGlobalString($node->value);
        $len    = $this->builder->constInt(IRType::I64, strlen($node->value));
        // Reference the global string pointer directly as a PTR-typed value
        $ptr    = new IRValue(IRType::PTR, $global);
        return $this->builder->call('fel_str_new', IRType::PTR, [$ptr, $len]);
    }

    private function compileBool(BooleanLiteral $node): IRValue {
        $c = new IRValue(IRType::I8, $node->value ? '1' : '0');
        return $this->builder->call('fel_val_bool', IRType::PTR, [$c]);
    }

    private function compileIdentifier(Identifier $node): ?IRValue {
        $ptr = $this->symbols->resolve($node->value);
        if (!$ptr) return null; // unknown identifier — skip (builtins handled at runtime)
        return $this->builder->load($ptr);
    }

    private function compileInfix(InfixExpression $node): ?IRValue {
        $left  = $this->compileNode($node->left);
        $right = $this->compileNode($node->right);
        if (!$left || !$right) return null;
        // Runtime handles all arithmetic/comparison on boxed values
        $op = $this->builder->constInt(IRType::I8, $this->opCode($node->operator));
        return $this->builder->call('fel_binop', IRType::PTR, [$op, $left, $right]);
    }

    private function compilePrefix(PrefixExpression $node): ?IRValue {
        $right = $this->compileNode($node->right);
        if (!$right) return null;
        $op = $this->builder->constInt(IRType::I8, $node->operator === '-' ? 1 : 0);
        return $this->builder->call('fel_unop', IRType::PTR, [$op, $right]);
    }

    private function compileIf(IfExpression $node): ?IRValue {
        $cond     = $this->compileNode($node->condition);
        if (!$cond) return null;
        // Get i8 from FelVal*, then trunc to i1 for br
        $condI8   = $this->builder->call('fel_is_truthy', IRType::I8, [$cond]);
        $condBool = $this->builder->trunc($condI8, IRType::I1);

        $thenLabel = 'if_then_' . $this->labelCount;
        $elseLabel = 'if_else_' . $this->labelCount;
        $endLabel  = 'if_end_'  . $this->labelCount;
        $this->labelCount++;

        $this->builder->br($condBool, $thenLabel, $node->alternative ? $elseLabel : $endLabel);

        // Then block
        $this->currentFn->newBlock($thenLabel);
        $this->builder = $this->currentFn->switchTo($thenLabel);
        $this->compileBlock($node->consequence);
        $this->builder->jump($endLabel);

        // Else block (if exists)
        if ($node->alternative) {
            $this->currentFn->newBlock($elseLabel);
            $this->builder = $this->currentFn->switchTo($elseLabel);
            $this->compileBlock($node->alternative);
            $this->builder->jump($endLabel);
        }

        // End block
        $this->currentFn->newBlock($endLabel);
        $this->builder = $this->currentFn->switchTo($endLabel);
        return null;
    }

    private function compileWhile(WhileExpression $node): ?IRValue {
        $condLabel = 'while_cond_' . $this->labelCount;
        $bodyLabel = 'while_body_' . $this->labelCount;
        $endLabel  = 'while_end_'  . $this->labelCount;
        $this->labelCount++;

        $this->builder->jump($condLabel);

        // Condition block
        $this->currentFn->newBlock($condLabel);
        $this->builder = $this->currentFn->switchTo($condLabel);
        $cond     = $this->compileNode($node->condition);
        if ($cond) {
            $condI8   = $this->builder->call('fel_is_truthy', IRType::I8, [$cond]);
            $condBool = $this->builder->trunc($condI8, IRType::I1);
        } else {
            $condBool = new IRValue(IRType::I1, '0');
        }
        $this->builder->br($condBool, $bodyLabel, $endLabel);

        // Body block
        $this->currentFn->newBlock($bodyLabel);
        $this->builder = $this->currentFn->switchTo($bodyLabel);
        $this->compileBlock($node->body);
        $this->builder->jump($condLabel);

        // End block
        $this->currentFn->newBlock($endLabel);
        $this->builder = $this->currentFn->switchTo($endLabel);
        return null;
    }

    private function compileCall(CallExpression $node): ?IRValue {
        // Special case: display(x) → call fel_display
        if ($node->function instanceof Identifier && $node->function->value === 'display') {
            $args = array_map(fn($a) => $this->compileNode($a), $node->arguments);
            foreach ($args as $arg) {
                if ($arg) $this->builder->call('fel_display', IRType::VOID, [$arg]);
            }
            return $this->builder->call('fel_val_null', IRType::PTR, []);
        }
        // Generic: look up fn pointer, call via fel_call
        $fn   = $this->compileNode($node->function);
        $args = array_map(fn($a) => $this->compileNode($a), $node->arguments);
        $args = array_filter($args);
        if (!$fn) return null;
        // For simplicity: placeholder return null — function calls beyond display
        // are a known limitation of the naive codegen (requires closures runtime support)
        return $this->builder->call('fel_val_null', IRType::PTR, []);
    }

    private function compileArray(ArrayLiteral $node): ?IRValue {
        $cap = $this->builder->constInt(IRType::I64, count($node->elements));
        $arr = $this->builder->call('fel_arr_new', IRType::PTR, [$cap]);
        if (!$arr) return null;

        // Root the array pointer during element construction so a GC cycle
        // triggered by element allocation cannot sweep the unrooted array.
        $tmpSlot = $this->builder->alloca(IRType::PTR, 'arr_tmp_' . $this->labelCount++);
        $this->builder->store($arr, $tmpSlot);
        $this->builder->call('fel_gc_push_root', IRType::VOID, [$tmpSlot]);
        $this->rootCount++;

        foreach ($node->elements as $el) {
            $val = $this->compileNode($el);
            if ($val && $arr) {
                $this->builder->call('fel_arr_push', IRType::VOID, [$arr, $val]);
            }
        }

        // Pop the temp root — the caller (e.g. compileLetStatement) will root
        // the value in its own let slot.
        $one = $this->builder->constInt(IRType::I32, 1);
        $this->builder->call('fel_gc_pop_roots', IRType::VOID, [$one]);
        $this->rootCount--;

        return $arr;
    }

    private function addMainWrapper(): void {
        $mainFn = new IRFunction('main', IRType::I32, [], isInternal: false);
        $this->module->addFunction($mainFn);
        $b = $mainFn->builder();
        // Initialize GC
        $b->call('fel_gc_init', IRType::VOID, []);
        // Call fel_main
        $exit = $b->call('fel_main', IRType::I32, []);
        // Shutdown GC
        $b->call('fel_gc_shutdown', IRType::VOID, []);
        // Return exit code
        $b->ret($exit);
    }

    private function opCode(string $op): int {
        return match($op) {
            '+'  => 1,  '-'  => 2,  '*'  => 3,  '/'  => 4,  '%'  => 5,
            '==' => 6,  '!=' => 7,  '<'  => 8,  '>'  => 9,  '<=' => 10, '>=' => 11,
            '&&' => 12, '||' => 13,
            default => 0,
        };
    }
}
