<?php
declare(strict_types=1);
namespace Fel\Evaluator;

use Fel\Evaluator\Operator\{InfixOperations, IntegerInfix, FloatInfix, StringInfix, PrefixOperations};
use Fel\Evaluator\Call\FunctionApplier;
use Fel\Loader\Importer;

final class EvaluatorFactory {
    public static function default(bool $sandbox = false): Evaluator {
        $values = Values::default();
        $infix  = new InfixOperations($values, [
            new IntegerInfix($values),
            new FloatInfix($values),
            new StringInfix($values),
        ]);
        $prefix   = new PrefixOperations($values);
        $applier  = new FunctionApplier($values);
        $builtins = new Builtins($values->null(), $values->true(), $values->false(), sandbox: $sandbox);
        $importer = new Importer();

        return new Evaluator($values, $infix, $prefix, $applier, $builtins, $importer);
    }
}
