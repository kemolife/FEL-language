<?php
declare(strict_types=1);
namespace Fel\Evaluator;

use Fel\Ast\Node;
use Fel\Ast\Node\{
    Program, ExpressionStatement, LetStatement, AssignStatement,
    ReturnStatement, BlockStatement,
    IntegerLiteral, FloatLiteral, StringLiteral, BooleanLiteral, NullLiteral,
    Identifier, PrefixExpression, InfixExpression,
    IfExpression, WhileExpression, ForInExpression,
    FunctionLiteral, CallExpression,
    ArrayLiteral, IndexExpression, HashLiteral,
    ImportStatement,
};
use Fel\Evaluator\Call\FunctionApplier;
use Fel\Evaluator\Operator\{InfixOperations, PrefixOperations};
use Fel\Loader\Importer;
use Fel\Object\{FelObject, Environment, Hashable, ObjectType};
use Fel\Object\Type\{
    IntegerObject, FloatObject, StringObject, BooleanObject, NullObject,
    ReturnValue, ErrorObject, FunctionObject, BuiltinObject,
    ArrayObject, HashObject, HashPair, GeneratorObject,
};

class Evaluator {
    public function __construct(
        private readonly Values           $values,
        private readonly InfixOperations  $infix,
        private readonly PrefixOperations $prefix,
        private readonly FunctionApplier  $applier,
        private readonly Builtins         $builtins,
        private readonly Importer         $importer,
    ) {}

    public function eval(Node $node, Environment $env): FelObject {
        return match(true) {
            $node instanceof Program             => $this->evalProgram($node, $env),
            $node instanceof ExpressionStatement => $this->eval($node->expression, $env),
            $node instanceof BlockStatement      => $this->evalBlockStatement($node, $env),
            $node instanceof LetStatement        => $this->evalLetStatement($node, $env),
            $node instanceof AssignStatement     => $this->evalAssignStatement($node, $env),
            $node instanceof ReturnStatement     => $this->evalReturnStatement($node, $env),
            $node instanceof IntegerLiteral      => new IntegerObject($node->value),
            $node instanceof FloatLiteral        => new FloatObject($node->value),
            $node instanceof StringLiteral       => new StringObject($node->value),
            $node instanceof BooleanLiteral      => $node->value ? $this->values->true() : $this->values->false(),
            $node instanceof NullLiteral         => $this->values->null(),
            $node instanceof Identifier          => $this->evalIdentifier($node, $env),
            $node instanceof PrefixExpression    => $this->evalPrefixExpression($node, $env),
            $node instanceof InfixExpression     => $this->evalInfixExpression($node, $env),
            $node instanceof IfExpression        => $this->evalIfExpression($node, $env),
            $node instanceof WhileExpression     => $this->evalWhileExpression($node, $env),
            $node instanceof ForInExpression     => $this->evalForInExpression($node, $env),
            $node instanceof FunctionLiteral     => $this->evalFunctionLiteral($node, $env),
            $node instanceof CallExpression      => $this->evalCallExpression($node, $env),
            $node instanceof ArrayLiteral        => $this->evalArrayLiteral($node, $env),
            $node instanceof IndexExpression     => $this->evalIndexExpression($node, $env),
            $node instanceof HashLiteral         => $this->evalHashLiteral($node, $env),
            $node instanceof ImportStatement     => $this->evalImportStatement($node, $env),
            default => $this->values->null(),
        };
    }

    private function evalProgram(Program $program, Environment $env): FelObject {
        $result = $this->values->null();
        foreach ($program->statements as $stmt) {
            $result = $this->eval($stmt, $env);
            if ($result instanceof ReturnValue) return $result->value;
            if ($result instanceof ErrorObject)  return $result;
        }
        return $result;
    }

    private function evalBlockStatement(BlockStatement $block, Environment $env): FelObject {
        $result = $this->values->null();
        foreach ($block->statements as $stmt) {
            $result = $this->eval($stmt, $env);
            if ($result instanceof ReturnValue || $result instanceof ErrorObject) {
                return $result;
            }
        }
        return $result;
    }

    private function evalLetStatement(LetStatement $node, Environment $env): FelObject {
        $val = $this->eval($node->value, $env);
        if ($val instanceof ErrorObject) return $val;
        $env->set($node->name->value, $val);
        return $this->values->null();
    }

    private function evalAssignStatement(AssignStatement $node, Environment $env): FelObject {
        $val = $this->eval($node->value, $env);
        if ($val instanceof ErrorObject) return $val;
        if (!$env->assign($node->name->value, $val)) {
            return new ErrorObject("identifier not found: {$node->name->value}");
        }
        return $this->values->null();
    }

    private function evalReturnStatement(ReturnStatement $node, Environment $env): FelObject {
        $val = $node->returnValue ? $this->eval($node->returnValue, $env) : $this->values->null();
        if ($val instanceof ErrorObject) return $val;
        return new ReturnValue($val);
    }

    private function evalIdentifier(Identifier $node, Environment $env): FelObject {
        if ($val = $env->get($node->value)) return $val;
        if ($builtin = $this->builtins->get($node->value)) return $builtin;
        return new ErrorObject("identifier not found: {$node->value}");
    }

    private function evalPrefixExpression(PrefixExpression $node, Environment $env): FelObject {
        $right = $this->eval($node->right, $env);
        if ($right instanceof ErrorObject) return $right;
        return $this->prefix->evaluate($node->operator, $right);
    }

    private function evalInfixExpression(InfixExpression $node, Environment $env): FelObject {
        $left = $this->eval($node->left, $env);
        if ($left instanceof ErrorObject) return $left;
        $right = $this->eval($node->right, $env);
        if ($right instanceof ErrorObject) return $right;

        $op = $node->operator;

        // short-circuit logical operators (eager: both operands already evaluated)
        if ($op === '&&') return $this->values->bool($this->values->isTruthy($left) && $this->values->isTruthy($right));
        if ($op === '||') return $this->values->bool($this->values->isTruthy($left) || $this->values->isTruthy($right));

        return $this->infix->evaluate($op, $left, $right);
    }

    private function evalIfExpression(IfExpression $node, Environment $env): FelObject {
        $cond = $this->eval($node->condition, $env);
        if ($cond instanceof ErrorObject) return $cond;
        if ($this->values->isTruthy($cond)) return $this->eval($node->consequence, $env);
        if ($node->alternative)     return $this->eval($node->alternative, $env);
        return $this->values->null();
    }

    private function evalWhileExpression(WhileExpression $node, Environment $env): FelObject {
        $result = $this->values->null();
        while (true) {
            $cond = $this->eval($node->condition, $env);
            if ($cond instanceof ErrorObject) return $cond;
            if (!$this->values->isTruthy($cond)) break;
            $result = $this->eval($node->body, $env);
            if ($result instanceof ErrorObject || $result instanceof ReturnValue) return $result;
        }
        return $result;
    }

    private function evalForInExpression(ForInExpression $node, Environment $env): FelObject {
        $iterable = $this->eval($node->iterable, $env);
        if ($iterable instanceof ErrorObject) return $iterable;

        if ($iterable instanceof ArrayObject) {
            return $this->iterateForIn($node, $env, $iterable->elements);
        }
        if ($iterable instanceof GeneratorObject) {
            return $this->iterateForIn($node, $env, $iterable->iterator());
        }
        return new ErrorObject("for-in requires ARRAY or GENERATOR, got {$iterable->type()->value}");
    }

    /** @param iterable<FelObject> $items */
    private function iterateForIn(ForInExpression $node, Environment $env, iterable $items): FelObject {
        $result = $this->values->null();
        foreach ($items as $element) {
            $loopEnv = Environment::enclosed($env);
            $loopEnv->set($node->variable->value, $element);
            $result = $this->eval($node->body, $loopEnv);
            if ($result instanceof ErrorObject || $result instanceof ReturnValue) return $result;
        }
        return $result;
    }

    private function evalFunctionLiteral(FunctionLiteral $node, Environment $env): FelObject {
        $params  = array_map(fn($p) => $p->value, $node->parameters);
        $bodySrc = $node->body->string();
        $body    = $node->body;
        $eval    = $this;
        return new FunctionObject(
            params:  $params,
            body:    function(Environment $callEnv) use ($body, $eval): FelObject {
                return $eval->eval($body, $callEnv);
            },
            bodySrc: $bodySrc,
            env:     $env,
        );
    }

    private function evalCallExpression(CallExpression $node, Environment $env): FelObject {
        $fn = $this->eval($node->function, $env);
        if ($fn instanceof ErrorObject) return $fn;
        $args = $this->evalExpressions($node->arguments, $env);
        if (count($args) === 1 && $args[0] instanceof ErrorObject) return $args[0];
        return $this->applier->apply($fn, $args);
    }

    /** @return FelObject[] */
    private function evalExpressions(array $exprs, Environment $env): array {
        $result = [];
        foreach ($exprs as $expr) {
            $val = $this->eval($expr, $env);
            if ($val instanceof ErrorObject) return [$val];
            $result[] = $val;
        }
        return $result;
    }

    private function evalArrayLiteral(ArrayLiteral $node, Environment $env): FelObject {
        $elements = $this->evalExpressions($node->elements, $env);
        if (count($elements) === 1 && $elements[0] instanceof ErrorObject) return $elements[0];
        return new ArrayObject($elements);
    }

    private function evalIndexExpression(IndexExpression $node, Environment $env): FelObject {
        $left  = $this->eval($node->left, $env);
        if ($left instanceof ErrorObject) return $left;
        $index = $this->eval($node->index, $env);
        if ($index instanceof ErrorObject) return $index;

        if ($left instanceof ArrayObject && $index instanceof IntegerObject) {
            $idx = $index->value;
            $max = count($left->elements) - 1;
            if ($idx < 0 || $idx > $max) return $this->values->null();
            return $left->elements[$idx];
        }
        if ($left instanceof HashObject) {
            if (!$index instanceof Hashable) {
                return new ErrorObject("unusable as hash key: {$index->type()->value}");
            }
            $pair = $left->pairs[$index->hashKey()] ?? null;
            return $pair?->value ?? $this->values->null();
        }
        return new ErrorObject("index operator not supported: {$left->type()->value}");
    }

    private function evalHashLiteral(HashLiteral $node, Environment $env): FelObject {
        $pairs = [];
        foreach ($node->pairs as [$keyNode, $valueNode]) {
            $key = $this->eval($keyNode, $env);
            if ($key instanceof ErrorObject) return $key;
            if (!$key instanceof Hashable) {
                return new ErrorObject("unusable as hash key: {$key->type()->value}");
            }
            $value = $this->eval($valueNode, $env);
            if ($value instanceof ErrorObject) return $value;
            $pairs[$key->hashKey()] = new HashPair($key, $value);
        }
        return new HashObject($pairs);
    }

    private function evalImportStatement(ImportStatement $node, Environment $env): FelObject {
        $module = $this->importer->load($node->path, $this);
        if ($module instanceof ErrorObject) return $module;

        // bind module to last path component (e.g. "stdlib/math" → "math")
        $name = basename($node->path);
        $env->set($name, $module);
        return $this->values->null();
    }

}
