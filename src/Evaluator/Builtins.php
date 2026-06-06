<?php
declare(strict_types=1);
namespace Fel\Evaluator;

use Fel\Object\{FelObject};
use Fel\Object\Type\{
    IntegerObject, FloatObject, StringObject, BooleanObject, NullObject,
    ArrayObject, HashObject, ErrorObject, BuiltinObject, GeneratorObject,
    StructInstanceObject, InterfaceObject,
};

class Builtins {
    /** @var array<string, BuiltinObject> */
    private array $builtins;

    public function __construct(
        private readonly NullObject    $null,
        private readonly BooleanObject $true,
        private readonly BooleanObject $false,
        private readonly bool          $sandbox = false,
    ) {
        $all = $this->build();
        if ($this->sandbox) {
            foreach (['read_file', 'write_file', 'readline'] as $blocked) {
                $all[$blocked] = new BuiltinObject(fn() => $this->err("{$blocked}: not available in sandbox mode"));
            }
        }
        $this->builtins = $all;
    }

    public function get(string $name): ?BuiltinObject {
        return $this->builtins[$name] ?? null;
    }

    private function err(string $msg): ErrorObject { return new ErrorObject($msg); }

    private function build(): array {
        return [
            'display' => new BuiltinObject(function(FelObject ...$args): FelObject {
                foreach ($args as $arg) echo $arg->inspect() . "\n";
                return $this->null;
            }),

            'implements' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("implements: want 2 args (value, interface)");
                [$value, $iface] = $args;
                if (!$iface instanceof InterfaceObject) return $this->err("implements: 2nd arg must be an interface");
                if (!$value instanceof StructInstanceObject) return $this->false;
                foreach ($iface->methods as $m) {
                    if (!isset($value->structType->methods[$m])) return $this->false;
                }
                return $this->true;
            }),

            'len' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("len: want 1 arg, got " . count($args));
                return match(true) {
                    $args[0] instanceof StringObject => new IntegerObject(mb_strlen($args[0]->value)),
                    $args[0] instanceof ArrayObject  => new IntegerObject(count($args[0]->elements)),
                    default => $this->err("len: unsupported type {$args[0]->type()->value}"),
                };
            }),

            'first' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("first: want 1 arg");
                if (!$args[0] instanceof ArrayObject) return $this->err("first: want ARRAY");
                return $args[0]->elements[0] ?? $this->null;
            }),

            'last' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("last: want 1 arg");
                if (!$args[0] instanceof ArrayObject) return $this->err("last: want ARRAY");
                $els = $args[0]->elements;
                return count($els) > 0 ? $els[count($els) - 1] : $this->null;
            }),

            'rest' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("rest: want 1 arg");
                if (!$args[0] instanceof ArrayObject) return $this->err("rest: want ARRAY");
                $els = $args[0]->elements;
                return count($els) > 0 ? new ArrayObject(array_slice($els, 1)) : $this->null;
            }),

            'push' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("push: want 2 args");
                if (!$args[0] instanceof ArrayObject) return $this->err("push: want ARRAY");
                return new ArrayObject([...$args[0]->elements, $args[1]]);
            }),

            'type' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("type: want 1 arg");
                return new StringObject($args[0]->type()->value);
            }),

            'to_int' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("to_int: want 1 arg");
                return match(true) {
                    $args[0] instanceof IntegerObject => $args[0],
                    $args[0] instanceof FloatObject   => new IntegerObject((int)$args[0]->value),
                    $args[0] instanceof StringObject  => is_numeric($args[0]->value)
                        ? new IntegerObject((int)$args[0]->value)
                        : $this->err("to_int: cannot convert \"{$args[0]->value}\""),
                    default => $this->err("to_int: unsupported type"),
                };
            }),

            'to_float' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("to_float: want 1 arg");
                return match(true) {
                    $args[0] instanceof FloatObject   => $args[0],
                    $args[0] instanceof IntegerObject => new FloatObject((float)$args[0]->value),
                    $args[0] instanceof StringObject  => is_numeric($args[0]->value)
                        ? new FloatObject((float)$args[0]->value)
                        : $this->err("to_float: cannot convert \"{$args[0]->value}\""),
                    default => $this->err("to_float: unsupported type"),
                };
            }),

            'to_str' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("to_str: want 1 arg");
                return new StringObject($args[0]->inspect());
            }),

            'split' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("split: want 2 args (str, sep)");
                if (!$args[0] instanceof StringObject) return $this->err("split: arg 1 must be STRING");
                if (!$args[1] instanceof StringObject) return $this->err("split: arg 2 must be STRING");
                $parts = explode($args[1]->value, $args[0]->value);
                return new ArrayObject(array_map(fn($p) => new StringObject($p), $parts));
            }),

            'join' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("join: want 2 args (arr, sep)");
                if (!$args[0] instanceof ArrayObject)  return $this->err("join: arg 1 must be ARRAY");
                if (!$args[1] instanceof StringObject) return $this->err("join: arg 2 must be STRING");
                $parts = array_map(fn($e) => $e->inspect(), $args[0]->elements);
                return new StringObject(implode($args[1]->value, $parts));
            }),

            'trim' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof StringObject) {
                    return $this->err("trim: want 1 STRING arg");
                }
                return new StringObject(trim($args[0]->value));
            }),

            'upper' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof StringObject) {
                    return $this->err("upper: want 1 STRING arg");
                }
                return new StringObject(mb_strtoupper($args[0]->value));
            }),

            'lower' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof StringObject) {
                    return $this->err("lower: want 1 STRING arg");
                }
                return new StringObject(mb_strtolower($args[0]->value));
            }),

            'contains' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("contains: want 2 args");
                if (!$args[0] instanceof StringObject) return $this->err("contains: arg 1 must be STRING");
                if (!$args[1] instanceof StringObject) return $this->err("contains: arg 2 must be STRING");
                return str_contains($args[0]->value, $args[1]->value) ? $this->true : $this->false;
            }),

            'substr' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) < 2) return $this->err("substr: want 2 or 3 args (str, start, len?)");
                if (!$args[0] instanceof StringObject)  return $this->err("substr: arg 1 must be STRING");
                if (!$args[1] instanceof IntegerObject) return $this->err("substr: arg 2 must be INTEGER");
                $len = isset($args[2]) && $args[2] instanceof IntegerObject ? $args[2]->value : null;
                $result = $len !== null
                    ? mb_substr($args[0]->value, $args[1]->value, $len)
                    : mb_substr($args[0]->value, $args[1]->value);
                return new StringObject($result === false ? '' : $result);
            }),

            'pad_left' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 3) return $this->err("pad_left: want 3 args (str, len, pad)");
                if (!$args[0] instanceof StringObject)  return $this->err("pad_left: arg 1 must be STRING");
                if (!$args[1] instanceof IntegerObject) return $this->err("pad_left: arg 2 must be INTEGER");
                if (!$args[2] instanceof StringObject)  return $this->err("pad_left: arg 3 must be STRING");
                return new StringObject(str_pad($args[0]->value, $args[1]->value, $args[2]->value, STR_PAD_LEFT));
            }),

            'pad_right' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 3) return $this->err("pad_right: want 3 args (str, len, pad)");
                if (!$args[0] instanceof StringObject)  return $this->err("pad_right: arg 1 must be STRING");
                if (!$args[1] instanceof IntegerObject) return $this->err("pad_right: arg 2 must be INTEGER");
                if (!$args[2] instanceof StringObject)  return $this->err("pad_right: arg 3 must be STRING");
                return new StringObject(str_pad($args[0]->value, $args[1]->value, $args[2]->value, STR_PAD_RIGHT));
            }),

            'keys' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("keys: want 1 arg");
                if (!$args[0] instanceof HashObject) return $this->err("keys: want HASH");
                $keys = array_map(fn($p) => $p->key, array_values($args[0]->pairs));
                return new ArrayObject($keys);
            }),

            'values' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("values: want 1 arg");
                if (!$args[0] instanceof HashObject) return $this->err("values: want HASH");
                $vals = array_map(fn($p) => $p->value, array_values($args[0]->pairs));
                return new ArrayObject($vals);
            }),

            'map' => new BuiltinObject(function(FelObject ...$args) use (&$eval): FelObject {
                if (count($args) !== 2) return $this->err("map: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("map: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("map: arg 2 must be FUNCTION");
                }
                $fn      = $args[1];
                $result  = [];
                foreach ($args[0]->elements as $el) {
                    $mapped = $this->callFn($fn, [$el]);
                    if ($mapped instanceof ErrorObject) return $mapped;
                    $result[] = $mapped;
                }
                return new ArrayObject($result);
            }),

            'filter' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("filter: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("filter: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("filter: arg 2 must be FUNCTION");
                }
                $fn     = $args[1];
                $result = [];
                foreach ($args[0]->elements as $el) {
                    $keep = $this->callFn($fn, [$el]);
                    if ($keep instanceof ErrorObject) return $keep;
                    if ($this->isTruthy($keep)) $result[] = $el;
                }
                return new ArrayObject($result);
            }),

            'reduce' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 3) return $this->err("reduce: want 3 args (arr, fn, initial)");
                if (!$args[0] instanceof ArrayObject) return $this->err("reduce: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("reduce: arg 2 must be FUNCTION");
                }
                $fn  = $args[1];
                $acc = $args[2];
                foreach ($args[0]->elements as $el) {
                    $acc = $this->callFn($fn, [$acc, $el]);
                    if ($acc instanceof ErrorObject) return $acc;
                }
                return $acc;
            }),

            'range' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 1 || $count > 3) return $this->err("range: want 1-3 args");
                [$start, $end, $step] = match($count) {
                    1 => [0, $args[0] instanceof IntegerObject ? $args[0]->value : 0, 1],
                    2 => [
                        $args[0] instanceof IntegerObject ? $args[0]->value : 0,
                        $args[1] instanceof IntegerObject ? $args[1]->value : 0,
                        1,
                    ],
                    3 => [
                        $args[0] instanceof IntegerObject ? $args[0]->value : 0,
                        $args[1] instanceof IntegerObject ? $args[1]->value : 0,
                        $args[2] instanceof IntegerObject ? $args[2]->value : 1,
                    ],
                };
                if ($step === 0) return $this->err("range: step cannot be 0");
                $result = [];
                for ($i = $start; $step > 0 ? $i < $end : $i > $end; $i += $step) {
                    $result[] = new IntegerObject($i);
                }
                return new ArrayObject($result);
            }),

            'lazy_range' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 1 || $count > 3) return $this->err("lazy_range: want 1-3 args");
                foreach ($args as $a) {
                    if (!$a instanceof IntegerObject) return $this->err("lazy_range: args must be INTEGER");
                }
                [$start, $end, $step] = match($count) {
                    1 => [0, $args[0]->value, 1],
                    2 => [$args[0]->value, $args[1]->value, 1],
                    3 => [$args[0]->value, $args[1]->value, $args[2]->value],
                };
                if ($step === 0) return $this->err("lazy_range: step cannot be 0");
                $factory = function() use ($start, $end, $step): \Iterator {
                    for ($i = $start; $step > 0 ? $i < $end : $i > $end; $i += $step) {
                        yield new IntegerObject($i);
                    }
                };
                return new GeneratorObject($factory, "lazy_range({$start}, {$end}, {$step})");
            }),

            'count_from' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 1 || $count > 2) return $this->err("count_from: want 1-2 args");
                foreach ($args as $a) {
                    if (!$a instanceof IntegerObject) return $this->err("count_from: args must be INTEGER");
                }
                $start = $args[0]->value;
                $step  = $count === 2 ? $args[1]->value : 1;
                if ($step === 0) return $this->err("count_from: step cannot be 0");
                $factory = function() use ($start, $step): \Iterator {
                    $i = $start;
                    while (true) {
                        yield new IntegerObject($i);
                        $i += $step;
                    }
                };
                return new GeneratorObject($factory, "count_from({$start}, {$step})");
            }),

            'take' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("take: want 2 args (seq, n)");
                if (!$args[1] instanceof IntegerObject) return $this->err("take: arg 2 must be INTEGER");
                $n = $args[1]->value;
                if ($n < 0) return $this->err("take: n must be >= 0");
                $result = [];
                if ($args[0] instanceof ArrayObject) {
                    $result = array_slice($args[0]->elements, 0, $n);
                } elseif ($args[0] instanceof GeneratorObject) {
                    $i = 0;
                    foreach ($args[0]->iterator() as $el) {
                        if ($i >= $n) break;
                        $result[] = $el;
                        $i++;
                    }
                } else {
                    return $this->err("take: arg 1 must be ARRAY or GENERATOR");
                }
                return new ArrayObject($result);
            }),

            'to_array' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("to_array: want 1 arg");
                if ($args[0] instanceof ArrayObject) return $args[0];
                if (!$args[0] instanceof GeneratorObject) return $this->err("to_array: want GENERATOR or ARRAY");
                $result = [];
                foreach ($args[0]->iterator() as $el) $result[] = $el;
                return new ArrayObject($result);
            }),

            'sqrt' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("sqrt: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("sqrt: want INT or FLOAT arg");
                return new FloatObject(sqrt($v));
            }),

            'pow' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("pow: want 2 args");
                $x = $this->numericVal($args[0]);
                $y = $this->numericVal($args[1]);
                if ($x === null || $y === null) return $this->err("pow: want INT or FLOAT args");
                if ($args[0] instanceof IntegerObject && $args[1] instanceof IntegerObject) {
                    return new IntegerObject((int)pow($x, $y));
                }
                return new FloatObject(pow($x, $y));
            }),

            'abs' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("abs: want 1 arg");
                return match(true) {
                    $args[0] instanceof IntegerObject => new IntegerObject(abs($args[0]->value)),
                    $args[0] instanceof FloatObject   => new FloatObject(abs($args[0]->value)),
                    default => $this->err("abs: want INT or FLOAT arg"),
                };
            }),

            'floor' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("floor: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("floor: want INT or FLOAT arg");
                return new IntegerObject((int)floor($v));
            }),

            'ceil' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("ceil: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("ceil: want INT or FLOAT arg");
                return new IntegerObject((int)ceil($v));
            }),

            'round' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 1 || $count > 2) return $this->err("round: want 1-2 args");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("round: want INT or FLOAT arg");
                if ($count === 2) {
                    if (!$args[1] instanceof IntegerObject) return $this->err("round: precision must be INT");
                    return new FloatObject(round($v, $args[1]->value));
                }
                return new IntegerObject((int)round($v));
            }),

            'min' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("min: want 2 args");
                $a = $this->numericVal($args[0]);
                $b = $this->numericVal($args[1]);
                if ($a === null || $b === null) return $this->err("min: want INT or FLOAT args");
                $smaller = $a <= $b ? $args[0] : $args[1];
                return match(true) {
                    $smaller instanceof IntegerObject => new IntegerObject($smaller->value),
                    default                           => new FloatObject($smaller->value),
                };
            }),

            'max' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("max: want 2 args");
                $a = $this->numericVal($args[0]);
                $b = $this->numericVal($args[1]);
                if ($a === null || $b === null) return $this->err("max: want INT or FLOAT args");
                $larger = $a >= $b ? $args[0] : $args[1];
                return match(true) {
                    $larger instanceof IntegerObject => new IntegerObject($larger->value),
                    default                          => new FloatObject($larger->value),
                };
            }),

            'log' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 1 || $count > 2) return $this->err("log: want 1-2 args");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("log: want INT or FLOAT arg");
                if ($count === 2) {
                    $base = $this->numericVal($args[1]);
                    if ($base === null) return $this->err("log: base must be INT or FLOAT");
                    return new FloatObject(log($v, $base));
                }
                return new FloatObject(log($v));
            }),

            'sin' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("sin: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("sin: want INT or FLOAT arg");
                return new FloatObject(sin($v));
            }),

            'cos' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("cos: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("cos: want INT or FLOAT arg");
                return new FloatObject(cos($v));
            }),

            'tan' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("tan: want 1 arg");
                $v = $this->numericVal($args[0]);
                if ($v === null) return $this->err("tan: want INT or FLOAT arg");
                return new FloatObject(tan($v));
            }),

            'sort' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof ArrayObject) {
                    return $this->err("sort: want 1 ARRAY arg");
                }
                $els = $args[0]->elements;
                usort($els, fn($a, $b) => $a->inspect() <=> $b->inspect());
                return new ArrayObject($els);
            }),

            'sort_by' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("sort_by: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("sort_by: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("sort_by: arg 2 must be FUNCTION");
                }
                $fn  = $args[1];
                $els = $args[0]->elements;
                $err = null;
                usort($els, function($a, $b) use ($fn, &$err): int {
                    if ($err !== null) return 0;
                    $ka = $this->callFn($fn, [$a]);
                    if ($ka instanceof ErrorObject) { $err = $ka; return 0; }
                    $kb = $this->callFn($fn, [$b]);
                    if ($kb instanceof ErrorObject) { $err = $kb; return 0; }
                    return $ka->inspect() <=> $kb->inspect();
                });
                return $err ?? new ArrayObject($els);
            }),

            'reverse' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof ArrayObject) {
                    return $this->err("reverse: want 1 ARRAY arg");
                }
                return new ArrayObject(array_reverse($args[0]->elements));
            }),

            'flatten' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof ArrayObject) {
                    return $this->err("flatten: want 1 ARRAY arg");
                }
                $result = [];
                foreach ($args[0]->elements as $el) {
                    if ($el instanceof ArrayObject) {
                        foreach ($el->elements as $inner) $result[] = $inner;
                    } else {
                        $result[] = $el;
                    }
                }
                return new ArrayObject($result);
            }),

            'unique' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof ArrayObject) {
                    return $this->err("unique: want 1 ARRAY arg");
                }
                $seen   = [];
                $result = [];
                foreach ($args[0]->elements as $el) {
                    $key = $el->inspect();
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $result[]   = $el;
                    }
                }
                return new ArrayObject($result);
            }),

            'zip' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("zip: want 2 args");
                if (!$args[0] instanceof ArrayObject) return $this->err("zip: arg 1 must be ARRAY");
                if (!$args[1] instanceof ArrayObject) return $this->err("zip: arg 2 must be ARRAY");
                $a      = $args[0]->elements;
                $b      = $args[1]->elements;
                $len    = min(count($a), count($b));
                $result = [];
                for ($i = 0; $i < $len; $i++) {
                    $result[] = new ArrayObject([$a[$i], $b[$i]]);
                }
                return new ArrayObject($result);
            }),

            'slice' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $count = count($args);
                if ($count < 2 || $count > 3) return $this->err("slice: want 2-3 args (arr, start, length?)");
                if (!$args[0] instanceof ArrayObject)   return $this->err("slice: arg 1 must be ARRAY");
                if (!$args[1] instanceof IntegerObject) return $this->err("slice: start must be INT");
                if ($count === 3 && !$args[2] instanceof IntegerObject) return $this->err("slice: length must be INT");
                $length = $count === 3 ? $args[2]->value : null;
                return new ArrayObject(array_slice($args[0]->elements, $args[1]->value, $length));
            }),

            'concat' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("concat: want 2 args");
                if (!$args[0] instanceof ArrayObject) return $this->err("concat: arg 1 must be ARRAY");
                if (!$args[1] instanceof ArrayObject) return $this->err("concat: arg 2 must be ARRAY");
                return new ArrayObject([...$args[0]->elements, ...$args[1]->elements]);
            }),

            'every' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("every: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("every: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("every: arg 2 must be FUNCTION");
                }
                $fn = $args[1];
                foreach ($args[0]->elements as $el) {
                    $r = $this->callFn($fn, [$el]);
                    if ($r instanceof ErrorObject) return $r;
                    if (!$this->isTruthy($r)) return $this->false;
                }
                return $this->true;
            }),

            'some' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("some: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("some: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("some: arg 2 must be FUNCTION");
                }
                $fn = $args[1];
                foreach ($args[0]->elements as $el) {
                    $r = $this->callFn($fn, [$el]);
                    if ($r instanceof ErrorObject) return $r;
                    if ($this->isTruthy($r)) return $this->true;
                }
                return $this->false;
            }),

            'find' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("find: want 2 args (arr, fn)");
                if (!$args[0] instanceof ArrayObject) return $this->err("find: arg 1 must be ARRAY");
                if (!$args[1] instanceof \Fel\Object\Type\FunctionObject
                    && !$args[1] instanceof BuiltinObject) {
                    return $this->err("find: arg 2 must be FUNCTION");
                }
                $fn = $args[1];
                foreach ($args[0]->elements as $el) {
                    $r = $this->callFn($fn, [$el]);
                    if ($r instanceof ErrorObject) return $r;
                    if ($this->isTruthy($r)) return $el;
                }
                return $this->null;
            }),

            // --- io builtins ---

            'read_file' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof StringObject) {
                    return $this->err("read_file: want 1 STRING arg (path)");
                }
                $path = $args[0]->value;
                if (!file_exists($path)) return $this->err("read_file: file not found: {$path}");
                $contents = file_get_contents($path);
                if ($contents === false) return $this->err("read_file: could not read: {$path}");
                return new StringObject($contents);
            }),

            'write_file' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 2) return $this->err("write_file: want 2 args (path, content)");
                if (!$args[0] instanceof StringObject) return $this->err("write_file: arg 1 must be STRING (path)");
                if (!$args[1] instanceof StringObject) return $this->err("write_file: arg 2 must be STRING (content)");
                $result = file_put_contents($args[0]->value, $args[1]->value);
                if ($result === false) return $this->err("write_file: could not write: {$args[0]->value}");
                return new IntegerObject($result);
            }),

            'readline' => new BuiltinObject(function(FelObject ...$args): FelObject {
                $line = fgets(STDIN);
                if ($line === false) return $this->null;
                return new StringObject(rtrim($line, "\n"));
            }),

            // --- json builtins ---

            'json_encode' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1) return $this->err("json_encode: want 1 arg");
                $toPhp = function(FelObject $obj) use (&$toPhp): mixed {
                    return match(true) {
                        $obj instanceof IntegerObject => $obj->value,
                        $obj instanceof FloatObject   => $obj->value,
                        $obj instanceof StringObject  => $obj->value,
                        $obj instanceof BooleanObject => $obj->value,
                        $obj instanceof NullObject    => null,
                        $obj instanceof ArrayObject   => array_map($toPhp, $obj->elements),
                        $obj instanceof HashObject    => (function() use ($obj, $toPhp): array {
                            $map = [];
                            foreach ($obj->pairs as $pair) {
                                $map[$pair->key->inspect()] = $toPhp($pair->value);
                            }
                            return $map;
                        })(),
                        default => $obj->inspect(),
                    };
                };
                $encoded = json_encode($toPhp($args[0]));
                if ($encoded === false) return $this->err("json_encode: encoding failed");
                return new StringObject($encoded);
            }),

            'json_decode' => new BuiltinObject(function(FelObject ...$args): FelObject {
                if (count($args) !== 1 || !$args[0] instanceof StringObject) {
                    return $this->err("json_decode: want 1 STRING arg");
                }
                $decoded = json_decode($args[0]->value, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    return $this->err("json_decode: " . json_last_error_msg());
                }
                $toFel = function(mixed $val) use (&$toFel): FelObject {
                    if (is_null($val))   return $this->null;
                    if (is_bool($val))   return $val ? $this->true : $this->false;
                    if (is_int($val))    return new IntegerObject($val);
                    if (is_float($val))  return new FloatObject($val);
                    if (is_string($val)) return new StringObject($val);
                    if (is_array($val)) {
                        if (array_is_list($val)) {
                            return new ArrayObject(array_map($toFel, $val));
                        }
                        $pairs = [];
                        foreach ($val as $k => $v) {
                            $keyObj = new StringObject((string)$k);
                            $pairs[$keyObj->hashKey()] = new \Fel\Object\Type\HashPair($keyObj, $toFel($v));
                        }
                        return new HashObject($pairs);
                    }
                    return $this->null;
                };
                return $toFel($decoded);
            }),
        ];
    }

    /** @param FelObject[] $args */
    private function callFn(FelObject $fn, array $args): FelObject {
        if ($fn instanceof \Fel\Object\Type\FunctionObject) {
            $env = \Fel\Object\Environment::enclosed($fn->env);
            foreach ($fn->params as $i => $param) {
                $env->set($param, $args[$i] ?? $this->null);
            }
            $result = ($fn->body)($env);
            return $result instanceof \Fel\Object\Type\ReturnValue ? $result->value : $result;
        }
        if ($fn instanceof BuiltinObject) {
            return ($fn->fn)(...$args);
        }
        return $this->err("not a function");
    }

    private function numericVal(FelObject $obj): int|float|null {
        return match(true) {
            $obj instanceof IntegerObject => $obj->value,
            $obj instanceof FloatObject   => $obj->value,
            default                       => null,
        };
    }

    private function isTruthy(FelObject $obj): bool {
        return match(true) {
            $obj instanceof NullObject    => false,
            $obj === $this->true          => true,
            $obj === $this->false         => false,
            default                       => true,
        };
    }
}
