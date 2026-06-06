<?php
declare(strict_types=1);
namespace Fel\Evaluator\Call;

use Fel\Evaluator\Values;
use Fel\Object\{Environment, FelObject};
use Fel\Object\Type\{FunctionObject, BuiltinObject, ReturnValue, ErrorObject, GeneratorObject};

final class FunctionApplier {
    public function __construct(private readonly Values $values) {}

    /** @param FelObject[] $args */
    public function apply(FelObject $fn, array $args): FelObject {
        if ($fn instanceof FunctionObject) {
            $extEnv = Environment::enclosed($fn->env);
            foreach ($fn->params as $i => $param) {
                $extEnv->set($param, $args[$i] ?? $this->values->null());
            }
            if ($fn->isGenerator) {
                return $this->makeGenerator($fn, $extEnv);
            }
            $result = ($fn->body)($extEnv);
            return $result instanceof ReturnValue ? $result->value : $result;
        }
        if ($fn instanceof BuiltinObject) {
            return ($fn->fn)(...$args);
        }
        return new ErrorObject("not a function: {$fn->type()->value}");
    }

    /**
     * A generator function runs its body inside a Fiber. Each `yield` suspends
     * the Fiber back to the driver below, producing one value lazily.
     */
    private function makeGenerator(FunctionObject $fn, Environment $extEnv): GeneratorObject {
        $factory = function() use ($fn, $extEnv): \Generator {
            $fiber = new \Fiber(function() use ($fn, $extEnv): void {
                ($fn->body)($extEnv);
            });
            $value = $fiber->start();
            while (!$fiber->isTerminated()) {
                if ($value instanceof FelObject) {
                    yield $value;
                }
                $value = $fiber->resume();
            }
        };
        return new GeneratorObject($factory, 'generator');
    }
}
