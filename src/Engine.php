<?php
declare(strict_types=1);
namespace Fel;

use Fel\Evaluator\{Evaluator, EvaluatorFactory};
use Fel\Object\{Environment, FelObject};
use Fel\Object\Type\{
    BuiltinObject, IntegerObject, FloatObject, StringObject,
    BooleanObject, NullObject, ArrayObject, ErrorObject,
};
use Fel\Parser\ParserFactory;

/**
 * Embeddable FEL engine for use in PHP applications.
 *
 * $engine = new Engine();
 * $engine->setVar('x', 42);
 * $engine->registerFunc('add', fn($a, $b) => $a + $b);
 * $result = $engine->eval('add(x, 10)');
 */
class Engine {
    private readonly Environment $env;
    private readonly Evaluator   $evaluator;
    private array $errors = [];

    public function __construct(private readonly bool $sandbox = false) {
        $this->env       = new Environment();
        $this->evaluator = EvaluatorFactory::default(sandbox: $this->sandbox);
    }

    public function eval(string $source): mixed {
        $this->errors = [];
        $parser  = ParserFactory::fromSource($source);
        $program = $parser->parseProgram();

        if ($parser->errors()) {
            $this->errors = $parser->errors();
            return null;
        }

        $result = $this->evaluator->eval($program, $this->env);

        if ($result instanceof ErrorObject) {
            $this->errors[] = $result->inspect();
            return null;
        }

        return $this->toPhp($result);
    }

    public function setVar(string $name, mixed $value): self {
        $this->env->set($name, $this->toFel($value));
        return $this;
    }

    public function getVar(string $name): mixed {
        $obj = $this->env->get($name);
        return $obj ? $this->toPhp($obj) : null;
    }

    public function loadExtension(Extension $extension): self {
        $extension->register($this);
        return $this;
    }

    public function registerFunc(string $name, callable $fn): self {
        $this->env->set($name, new BuiltinObject(function(FelObject ...$args) use ($fn): FelObject {
            $phpArgs = array_map(fn($a) => $this->toPhp($a), $args);
            $result  = $fn(...$phpArgs);
            return $this->toFel($result);
        }));
        return $this;
    }

    public function errors(): array { return $this->errors; }
    public function hasErrors(): bool { return !empty($this->errors); }

    public function reset(): self {
        // clear user-set vars but keep builtins (evaluator holds builtins internally)
        return new self();
    }

    private function toFel(mixed $value): FelObject {
        return match(true) {
            is_int($value)   => new IntegerObject($value),
            is_float($value) => new FloatObject($value),
            is_string($value) => new StringObject($value),
            is_bool($value)  => new BooleanObject($value),
            is_null($value)  => new NullObject(),
            is_array($value) && array_is_list($value) => new ArrayObject(
                array_map(fn($v) => $this->toFel($v), $value)
            ),
            is_array($value) => new \Fel\Object\Type\HashObject(
                array_combine(
                    array_map(fn($k) => 'str:' . $k, array_keys($value)),
                    array_map(fn($k, $v) => new \Fel\Object\Type\HashPair(
                        new StringObject((string)$k), $this->toFel($v)
                    ), array_keys($value), $value)
                )
            ),
            default => new NullObject(),
        };
    }

    private function toPhp(FelObject $obj): mixed {
        return match(true) {
            $obj instanceof IntegerObject => $obj->value,
            $obj instanceof FloatObject   => $obj->value,
            $obj instanceof StringObject  => $obj->value,
            $obj instanceof BooleanObject => $obj->value,
            $obj instanceof NullObject    => null,
            $obj instanceof ArrayObject   => array_map(fn($e) => $this->toPhp($e), $obj->elements),
            $obj instanceof \Fel\Object\Type\HashObject => array_combine(
                array_map(fn($p) => $this->toPhp($p->key), array_values($obj->pairs)),
                array_map(fn($p) => $this->toPhp($p->value), array_values($obj->pairs))
            ),
            $obj instanceof \Fel\Object\Type\StructInstanceObject => array_map(
                fn($v) => $this->toPhp($v), $obj->fields
            ),
            default => $obj->inspect(),
        };
    }
}
