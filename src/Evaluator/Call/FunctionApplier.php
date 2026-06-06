<?php
declare(strict_types=1);
namespace Fel\Evaluator\Call;

use Fel\Evaluator\Values;
use Fel\Object\{Environment, FelObject};
use Fel\Object\Type\{FunctionObject, BuiltinObject, ReturnValue, ErrorObject};

final class FunctionApplier {
    public function __construct(private readonly Values $values) {}

    /** @param FelObject[] $args */
    public function apply(FelObject $fn, array $args): FelObject {
        if ($fn instanceof FunctionObject) {
            $extEnv = Environment::enclosed($fn->env);
            foreach ($fn->params as $i => $param) {
                $extEnv->set($param, $args[$i] ?? $this->values->null());
            }
            $result = ($fn->body)($extEnv);
            return $result instanceof ReturnValue ? $result->value : $result;
        }
        if ($fn instanceof BuiltinObject) {
            return ($fn->fn)(...$args);
        }
        return new ErrorObject("not a function: {$fn->type()->value}");
    }
}
