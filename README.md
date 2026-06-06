# FEL-language

**FEL** (Flexible Executed Language) — a tree-walking interpreter with an LLVM IR compiler backend. Written in PHP 8.1+.

## Requirements

- PHP 8.1+
- Composer
- clang (for LLVM compilation only)

## Install

```bash
composer install
```

## CLI Usage

```bash
# Run a script
php bin/fel script.fel

# Start REPL
php bin/fel

# Compile to LLVM IR
php bin/fel --compile script.fel
# produces script.ll — then build native binary:
clang -O2 script.ll src/Compiler/Runtime/fel_runtime.c -o script
```

## Examples

Working scripts in `examples/`. Run any with `php bin/fel examples/<name>.fel`.

| Script | Demonstrates |
|---|---|
| `01_hello.fel` | Variables, strings, arithmetic |
| `02_fibonacci.fel` | Recursion + iterative loop |
| `03_fizzbuzz.fel` | for-in, range, if/else, modulo |
| `04_higher_order.fel` | map / filter / reduce |
| `05_strings.fel` | String ops + `stdlib/string` |
| `06_data.fel` | Hashes, nested data, sort, unique |
| `07_closures.fel` | Closures, captured state |
| `08_math.fel` | `stdlib/math` |
| `09_packages.fel` | `date` package |
| `10_json.fel` | `stdlib/json` decode/encode |
| `11_generators.fel` | Lazy generators, infinite sequences |
| `compile_demo.fel` | Numeric subset that compiles to a **native binary** |

See `examples/README.md` for details.

## Language

Syntax tour lives in the example scripts — see the table above and
`examples/README.md`. Language rules in brief:

- `//` comments; statements end with `;` (last expression in a block is its return value).
- `let x = ...;` declares, `x = ...;` reassigns. Functions: `fn(a, b) { ... }`.
- `if`/`else`, `while`, `for (x in ...)`.
- Arrays `[1, 2, 3]`, hashes `{"k": v}`. Read with `arr[i]`, `hash["k"]`, or `hash.k`.
  Hashes are immutable in place — build a new hash literal instead.
- `import "stdlib/<name>"` binds a module (e.g. `import "stdlib/math"` exposes `math.sqrt`).
  Stdlib modules: `math`, `string`, `array`, `json`, `io`, `types`.

### Built-in functions

| Function | Description |
|---|---|
| `display(x)` | Print value |
| `len(x)` | Length of string or array |
| `type(x)` | Type name as string |
| `to_int(x)` / `to_float(x)` / `to_str(x)` | Type conversion |
| `first(arr)` / `last(arr)` / `rest(arr)` | Array access |
| `push(arr, val)` | Append to array (returns new array) |
| `keys(hash)` / `values(hash)` | Hash keys/values as arrays |
| `split(str, sep)` / `join(arr, sep)` | String/array conversion |
| `trim(s)` / `upper(s)` / `lower(s)` | String ops |
| `contains(str, sub)` | Substring check |
| `range(end)` / `range(start, end)` / `range(start, end, step)` | Integer range (eager — full array) |
| `lazy_range(...)` / `count_from(start, step?)` | Lazy generators (on-demand; `count_from` is infinite) |
| `take(seq, n)` / `to_array(gen)` | Bound / materialize a generator |
| `map(arr, fn)` / `filter(arr, fn)` / `reduce(arr, fn, init)` | Higher-order array |
| `sort(arr)` / `sort_by(arr, fn)` / `reverse(arr)` | Sorting |
| `flatten(arr)` / `unique(arr)` | Array reshaping |
| `zip(a, b)` / `concat(a, b)` / `slice(arr, start, len?)` | Array combining |
| `every(arr, fn)` / `some(arr, fn)` / `find(arr, fn)` | Predicates |
| `sqrt(x)` / `pow(x, y)` / `abs(x)` | Math |
| `floor(x)` / `ceil(x)` / `round(x)` | Rounding |
| `min(a, b)` / `max(a, b)` / `log(x)` | Math |
| `sin(x)` / `cos(x)` / `tan(x)` | Trigonometry |

## Embedding in PHP

```php
use Fel\Engine;

$engine = new Engine();
$engine->setVar('user', ['age' => 25, 'country' => 'US']);
$engine->registerFunc('send_email', fn($to) => mail($to, 'Hi', '...'));

$result = $engine->eval('
  if (user["age"] >= 18) {
    send_email("hi@example.com");
    "sent"
  } else {
    "blocked"
  }
');

echo $result;  // "sent"

if ($engine->hasErrors()) {
    print_r($engine->errors());
}
```

### Extension classes

```php
use Fel\{Engine, Extension};

class HttpExtension implements Extension {
    public function register(Engine $engine): void {
        $engine->registerFunc('http_get', fn(string $url): string => file_get_contents($url));
    }
}

$engine = new Engine();
$engine->loadExtension(new HttpExtension());
$engine->eval('display(http_get("https://example.com"))');
```

### Sandbox mode

```php
// Blocks all file I/O and system calls
$engine = new Engine(sandbox: true);
$result = $engine->eval($untrustedCode);
```

## Package System

FEL has a native package system — no Composer, no vendor directory.

### Packages

```bash
fel add http        # add package to fel.toml + install
fel add date
fel remove http     # remove package
fel install         # install all deps from fel.toml
fel list            # list installed packages
```

### Using packages in FEL

```fel
import "http";
let body = http.get("https://api.example.com/data");
display(body);

import "date";
display(date.today());          // "2024-01-15"
display(date.format(date.now(), "Y-m-d H:i:s"));
```

### `fel.toml`

Every project has a `fel.toml` manifest:

```toml
[package]
name = "my-project"
version = "1.0.0"
description = "My FEL project"

[dependencies]
http = "1.0.0"
date = "1.0.0"
```

### Writing a package

```
packages/
  mypkg/
    fel.toml     # name, version, description
    index.fel      # must end with a hash literal (the module)
    native.php     # optional: return ['func_name' => callable]
```

`native.php` exposes PHP functions to FEL:

```php
<?php
return [
    'redis_get' => fn(string $key): string => $redis->get($key),
    'redis_set' => fn(string $key, string $val): bool => $redis->set($key, $val),
];
```

`index.fel` wraps them into a module hash:

```fel
{
  "get": fn(key) { redis_get(key) },
  "set": fn(key, val) { redis_set(key, val) }
}
```

Then users do:

```fel
import "redis";
redis.set("counter", "42");
display(redis.get("counter"));
```

### Bundled packages

| Package | Functions |
|---|---|
| `http` | `get(url)`, `post(url, body)` |
| `date` | `now()`, `today()`, `format(ts, fmt)`, `parse(str)`, `diff(a, b)`, `add(ts, secs)` |

### Global package cache

Packages also resolve from `~/.fel/packages/<name>/` — install once, use in any project.

## LLVM IR Compilation

Compiles FEL to LLVM IR text (`.ll`) using a fully-boxed `FelVal*` value strategy.

```bash
php bin/fel --compile examples/compile_demo.fel
# Produces examples/compile_demo.ll

# Build native binary (requires clang)
clang -O2 examples/compile_demo.ll src/Compiler/Runtime/fel_runtime.c -o compile_demo
./compile_demo
```

The native binary needs no PHP at runtime — it's machine code. A mark-and-sweep
GC (in `fel_runtime.c`) reclaims memory; CodeGen emits GC roots at every scope.

**Compiler covers a numeric subset.** What works natively today:
- integer / float arithmetic, variables, reassignment
- `if`/`else`, `while`
- arrays of numbers, `display` of numbers

What is interpreter-only (NOT yet in the native backend):
- string concatenation and string builtins
- generic builtins (`to_str`, `len`, `push`, math fns, ...) — only `display` is wired
- index expressions, hashes, user-defined function calls, generators

Run `examples/compile_demo.fel` for a program that compiles and runs natively.
Anything richer — use the interpreter (`php bin/fel script.fel`). The native
pipeline is verified end-to-end by `tests/Compiler/NativeCompileTest.php`
(skipped automatically when clang is absent).

## Project structure

```
bin/fel                   CLI entry point
src/
  Lexer/Lexer.php           Tokenizer
  Parser/Parser.php         Pratt parser → AST
  Ast/Node/                 AST node classes
  Evaluator/
    Evaluator.php           Tree-walking interpreter
    Builtins.php            Built-in functions
  Object/                   Runtime value types
  Loader/
    Importer.php            Module loader
    ModuleCache.php         Parse-once cache
  Compiler/
    Compiler.php            Orchestrates compilation
    CodeGen.php             AST → LLVM IR
    SymbolTable.php         Variable scoping for codegen
    TypeInference.php       Type inference (stub)
    IR/                     LLVM IR builder scaffolding
    Runtime/
      fel_runtime.c/h       C runtime library
  Engine.php                Embeddable PHP API
  Extension.php             Extension interface
stdlib/
  math.fel / string.fel / array.fel / io.fel / json.fel / types.fel
```
