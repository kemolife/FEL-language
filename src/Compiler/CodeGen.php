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
    /** @var array<string, array{node: FunctionLiteral, arity: int}> top-level user functions */
    private array $userFns = [];
    /** When compiling inside a user function body, suppress top-level GC-root bookkeeping. */
    private bool $inUserFunction = false;

    public function __construct(private readonly string $moduleName = 'fel_output') {
        $this->symbols = new SymbolTable();
        $this->module  = new IRModule($this->moduleName);
    }

    public function compile(Program $program): IRModule {
        IRValue::reset();

        // Pre-register top-level `let name = fn(...)` so calls (incl. recursion) resolve.
        $this->collectFunctions($program);
        foreach ($this->userFns as $name => $info) {
            $this->compileUserFunction($name, $info['node']);
        }

        // Create @fel_main() function
        $mainFn = new IRFunction('fel_main', IRType::I32);
        $this->module->addFunction($mainFn);
        $this->currentFn = $mainFn;
        $this->builder   = $mainFn->builder();

        foreach ($program->statements as $stmt) {
            // function definitions are compiled separately above
            if ($stmt instanceof LetStatement && $stmt->value instanceof FunctionLiteral) {
                continue;
            }
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
        $this->symbols->define($node->name->value, $ptr);
        if (!$this->inUserFunction) {
            // Register as GC root so mark phase can find this variable
            $this->builder->call('fel_gc_push_root', IRType::VOID, [$ptr]);
            $this->rootCount++;   // track roots pushed in current scope
        }
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
            $ptr = $this->builder->alloca(IRType::PTR, $node->name->value . '_' . $this->labelCount++);
            $this->builder->store($val, $ptr);
            $this->symbols->define($node->name->value, $ptr);
            if (!$this->inUserFunction) {
                // Register as GC root so the mark phase can find this variable
                $this->builder->call('fel_gc_push_root', IRType::VOID, [$ptr]);
                $this->rootCount++;
            }
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
        if (!$left) return null;
        // Keep the left operand alive across right-hand evaluation: a nested call
        // there can trigger a GC cycle that would otherwise sweep this temporary.
        $rootSlot = $this->spillRoot($left);
        $right = $this->compileNode($node->right);
        if (!$right) { $this->popRoots(1); return null; }
        $op = $this->builder->constInt(IRType::I8, $this->opCode($node->operator));
        $res = $this->builder->call('fel_binop', IRType::PTR, [$op, $left, $right]);
        $this->popRoots(1);
        return $res;
    }

    /** Spill a value into a fresh alloca slot and register it as a transient GC root. */
    private function spillRoot(IRValue $val): IRValue {
        $slot = $this->builder->alloca(IRType::PTR, 'troot_' . $this->labelCount++);
        $this->builder->store($val, $slot);
        $this->builder->call('fel_gc_push_root', IRType::VOID, [$slot]);
        return $slot;
    }

    /** Pop n transient roots (balanced within a single expression; does not touch rootCount). */
    private function popRoots(int $n): void {
        if ($n <= 0) return;
        $c = $this->builder->constInt(IRType::I32, $n);
        $this->builder->call('fel_gc_pop_roots', IRType::VOID, [$c]);
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
        // Direct call to a known top-level user function (supports recursion).
        if ($node->function instanceof Identifier && isset($this->userFns[$node->function->value])) {
            $name  = $node->function->value;
            $arity = $this->userFns[$name]['arity'];
            $args  = [];
            $rooted = 0;
            foreach ($node->arguments as $a) {
                $val = $this->compileNode($a) ?? $this->builder->call('fel_val_null', IRType::PTR, []);
                $args[] = $val;
                // keep already-evaluated args alive while later args (which may
                // allocate / trigger GC) are computed
                $this->spillRoot($val);
                $rooted++;
            }
            // pad missing args with null
            while (count($args) < $arity) {
                $args[] = $this->builder->call('fel_val_null', IRType::PTR, []);
            }
            $args = array_slice($args, 0, $arity);
            $res = $this->builder->call('fel_fn_' . $name, IRType::PTR, $args);
            $this->popRoots($rooted);
            return $res;
        }

        // Other callables (function values, higher-order, closures) are not yet
        // supported by the native backend — they remain interpreter-only.
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

    /** Register every top-level `let name = fn(...)` for direct/recursive native calls. */
    private function collectFunctions(Program $program): void {
        foreach ($program->statements as $stmt) {
            if ($stmt instanceof LetStatement && $stmt->value instanceof FunctionLiteral) {
                $this->userFns[$stmt->name->value] = [
                    'node'  => $stmt->value,
                    'arity' => count($stmt->value->parameters),
                ];
            }
        }
    }

    /** Compile a top-level user function to an LLVM function returning FelVal* (ptr). */
    private function compileUserFunction(string $name, FunctionLiteral $node): void {
        $params = [];
        foreach ($node->parameters as $i => $_) {
            $params["p{$i}"] = IRType::PTR;
        }
        $fn = new IRFunction('fel_fn_' . $name, IRType::PTR, $params);
        $this->module->addFunction($fn);

        // swap compilation context to this function
        $savedFn      = isset($this->currentFn) ? $this->currentFn : null;
        $savedBuilder = isset($this->builder)   ? $this->builder   : null;
        $savedInFn    = $this->inUserFunction;
        $this->currentFn      = $fn;
        $this->builder        = $fn->builder();
        $this->inUserFunction = true;
        $this->symbols->push();

        // bind parameters: copy incoming SSA arg into an alloca slot
        foreach ($node->parameters as $i => $param) {
            $arg  = new IRValue(IRType::PTR, "%p{$i}");
            $slotName = 'arg_' . $param->value . '_' . $this->labelCount++;
            $slot = $this->builder->alloca(IRType::PTR, $slotName);
            $this->builder->store($arg, $slot);
            $this->symbols->define($param->value, $slot);
        }

        // compile the body in return position (every path ends in `ret`)
        $this->compileReturnPosition($node->body);

        $this->symbols->pop();
        $this->inUserFunction = $savedInFn;
        if ($savedFn !== null)      $this->currentFn = $savedFn;
        if ($savedBuilder !== null) $this->builder   = $savedBuilder;
    }

    /**
     * Compile a node such that its value becomes the function's return value.
     * Handles tail if/else (each branch returns) so recursion works without phi.
     */
    private function compileReturnPosition(Node $node): void {
        if ($node instanceof BlockStatement) {
            $this->symbols->push();
            $stmts = $node->statements;
            $count = count($stmts);
            if ($count === 0) {
                $this->emitReturn(null);
            } else {
                for ($i = 0; $i < $count - 1; $i++) {
                    $this->compileNode($stmts[$i]);
                }
                $this->compileReturnPosition($stmts[$count - 1]);
            }
            $this->symbols->pop();
            return;
        }
        if ($node instanceof ExpressionStatement) {
            $this->compileReturnPosition($node->expression);
            return;
        }
        if ($node instanceof ReturnStatement) {
            $val = $node->returnValue ? $this->compileNode($node->returnValue) : null;
            $this->emitReturn($val);
            return;
        }
        if ($node instanceof IfExpression) {
            $cond = $this->compileNode($node->condition);
            if (!$cond) { $this->emitReturn(null); return; }
            $condI8   = $this->builder->call('fel_is_truthy', IRType::I8, [$cond]);
            $condBool = $this->builder->trunc($condI8, IRType::I1);

            $thenLabel = 'rt_then_' . $this->labelCount;
            $elseLabel = 'rt_else_' . $this->labelCount;
            $this->labelCount++;

            $this->builder->br($condBool, $thenLabel, $elseLabel);

            $this->currentFn->newBlock($thenLabel);
            $this->builder = $this->currentFn->switchTo($thenLabel);
            $this->compileReturnPosition($node->consequence);

            $this->currentFn->newBlock($elseLabel);
            $this->builder = $this->currentFn->switchTo($elseLabel);
            if ($node->alternative) {
                $this->compileReturnPosition($node->alternative);
            } else {
                $this->emitReturn(null);
            }
            return;
        }
        // any other expression: compile to a value and return it
        $val = $this->compileNode($node);
        $this->emitReturn($val);
    }

    private function emitReturn(?IRValue $val): void {
        if (!$val) {
            $val = $this->builder->call('fel_val_null', IRType::PTR, []);
        }
        $this->builder->ret($val);
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
