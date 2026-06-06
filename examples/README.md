# FEL Examples

Well-commented, working example scripts demonstrating the FEL language.
Each script is self-contained. Run any of them with the `fel` interpreter
from the project root:

```bash
php bin/fel examples/<script>.fel
```

## Scripts

| Script | Description | Run |
| --- | --- | --- |
| `01_hello.fel` | Variables, strings, `display`, arithmetic operators | `php bin/fel examples/01_hello.fel` |
| `02_fibonacci.fel` | Recursion and a `while`-loop iterative version (first 10 Fibonacci numbers) | `php bin/fel examples/02_fibonacci.fel` |
| `03_fizzbuzz.fel` | Control flow: `for-in` over `range`, `if`/`else`, modulo (FizzBuzz 1..20) | `php bin/fel examples/03_fizzbuzz.fel` |
| `04_higher_order.fel` | `map` / `filter` / `reduce` and passing functions as values | `php bin/fel examples/04_higher_order.fel` |
| `05_strings.fel` | String ops: `split`, `join`, `upper`, `lower`, `trim`, `contains`, plus `stdlib/string` | `php bin/fel examples/05_strings.fel` |
| `06_data.fel` | Hashes and arrays: user records, key iteration, nested data, `sort_by`, `unique` | `php bin/fel examples/06_data.fel` |
| `07_closures.fel` | Closures: `make_counter` and `make_adder` capturing private state | `php bin/fel examples/07_closures.fel` |
| `08_math.fel` | `stdlib/math`: `sqrt`, `pow`, `pi`, circle area, Pythagorean theorem | `php bin/fel examples/08_math.fel` |
| `09_packages.fel` | The `date` package: `now`, `today`, `format`, `add` | `php bin/fel examples/09_packages.fel` |
| `10_json.fel` | `stdlib/json`: decode a JSON string, read fields, re-encode | `php bin/fel examples/10_json.fel` |
| `11_generators.fel` | Lazy builtins: `lazy_range`, `count_from`, `take`, `to_array` | `php bin/fel examples/11_generators.fel` |
| `12_http.fel` | The `http` package: `get` / `post` | `php bin/fel examples/12_http.fel` |
| `13_structs.fel` | Go-style structs, receiver methods, method chaining, interfaces + `implements` | `php bin/fel examples/13_structs.fel` |
| `14_control_flow.fel` | `break`/`continue`, `else if`, `match` + `_` wildcard, compound assignment | `php bin/fel examples/14_control_flow.fel` |
| `15_generators_errors.fel` | User `yield` generators and `try`/`catch`/`throw` error handling | `php bin/fel examples/15_generators_errors.fel` |

## Notes

- Comments use `//`.
- The last expression in a function/block is its return value; `return` is optional.
- Hashes and arrays are read with `arr[i]`, `hash["key"]`, or `hash.key`.
  Hashes are immutable in place (no `hash[k] = v`); build a new hash literal instead.
- `import "..."` statements take no trailing semicolon and bind a module name
  (e.g. `import "stdlib/math"` exposes `math.sqrt`).
