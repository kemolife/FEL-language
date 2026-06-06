<?php
declare(strict_types=1);
namespace Fel\Repl;

use Fel\Evaluator\{Evaluator, EvaluatorFactory};
use Fel\Object\{Environment};
use Fel\Object\Type\NullObject;
use Fel\Parser\ParserFactory;

class Repl {
    private readonly Environment $env;
    private readonly Evaluator   $evaluator;
    private bool $interrupted = false;

    public function __construct() {
        $this->env       = new Environment();
        $this->evaluator = EvaluatorFactory::default();
    }

    public function start(): void {
        // Ctrl-C aborts the current line instead of killing the REPL.
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void { $this->interrupted = true; });
        }

        $historyFile = (getenv('HOME') ?: sys_get_temp_dir()) . '/.fel_history';
        if (function_exists('readline_read_history') && file_exists($historyFile)) {
            readline_read_history($historyFile);
        }
        $this->setupCompletion();

        echo "FEL REPL v0.1.0\n";
        echo "Arrow keys: history + cursor. Ctrl-A/E/K/U, Ctrl-R search, Ctrl-D exit.\n";
        echo "Type 'exit' to quit, 'help' for help.\n\n";

        while (true) {
            $line = $this->readInput('> ');
            if ($line === false) { echo "\n"; break; }   // Ctrl-D / EOF
            $line = rtrim($line, "\n\r");

            if ($line === 'exit' || $line === 'quit') break;
            if ($line === '') continue;
            if ($line === 'help') {
                $this->printHelp();
                continue;
            }

            // Multi-line accumulation on unbalanced braces/parens/brackets.
            $buffer = $line;
            while (!$this->isBalanced($buffer)) {
                $cont = $this->readInput('... ');
                if ($cont === false) break;
                $buffer .= "\n" . rtrim($cont, "\n\r");
            }

            if (function_exists('readline_add_history')) {
                readline_add_history($buffer);
            }

            $output = $this->evalSource($buffer);
            if ($output !== null) echo $output . "\n";
        }

        if (function_exists('readline_write_history')) {
            readline_write_history($historyFile);
        }
    }

    private function readInput(string $prompt): string|false {
        if (function_exists('readline')) {
            $line = readline($prompt);   // returns false on EOF (Ctrl-D)
            if ($line === false && $this->interrupted) {
                $this->interrupted = false;   // Ctrl-C: clear line, not EOF
                echo "\n";
                return '';
            }
            return $line;
        }
        echo $prompt;
        $line = fgets(STDIN);
        return $line === false ? false : rtrim($line, "\n\r");
    }

    private function setupCompletion(): void {
        if (!function_exists('readline_completion_function')) return;
        $words = [
            // keywords
            'let', 'fn', 'if', 'else', 'while', 'for', 'in', 'return',
            'true', 'false', 'import', 'break', 'continue',
            // builtins
            'display', 'len', 'first', 'last', 'rest', 'push', 'type',
            'to_int', 'to_float', 'to_str', 'split', 'join', 'trim',
            'upper', 'lower', 'contains', 'keys', 'values', 'range',
            'map', 'filter', 'reduce', 'sort', 'sort_by', 'reverse',
            'flatten', 'unique', 'zip', 'slice', 'concat', 'every',
            'some', 'find', 'sqrt', 'pow', 'abs', 'floor', 'ceil',
            'round', 'min', 'max', 'log', 'sin', 'cos', 'tan',
            'lazy_range', 'count_from', 'take', 'to_array',
            'read_file', 'write_file', 'readline', 'json_encode', 'json_decode',
        ];
        readline_completion_function(function (string $input, int $index) use ($words): array {
            // readline passes the current word being completed.
            if ($input === '') return $words;
            return array_values(array_filter($words, fn($w) => str_starts_with($w, $input)));
        });
    }

    private function isBalanced(string $code): bool {
        $depth = 0;
        $inStr = false;
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $c = $code[$i];
            if ($inStr) {
                if ($c === '\\') { $i++; continue; }  // skip escaped char
                if ($c === '"') $inStr = false;
                continue;
            }
            if ($c === '"') { $inStr = true; continue; }
            if ($c === '{' || $c === '(' || $c === '[') $depth++;
            if ($c === '}' || $c === ')' || $c === ']') $depth--;
        }
        return $depth <= 0;
    }

    private function evalSource(string $source): ?string {
        $parser  = ParserFactory::fromSource($source);
        $program = $parser->parseProgram();

        if ($errors = $parser->errors()) {
            foreach ($errors as $err) echo "  parse error: {$err}\n";
            return null;
        }

        $result = $this->evaluator->eval($program, $this->env);

        if ($result instanceof NullObject) return null;
        return $result->inspect();
    }

    private function printHelp(): void {
        echo <<<HELP
        FEL (Flexible Executed Language) v0.1.0

        Syntax:
          let x = 5;                   variable declaration
          x = 10;                      reassignment
          if (x > 5) { ... }           conditional
          while (x > 0) { x = x - 1; } loop
          for (item in arr) { ... }    iteration
          fn(x, y) { x + y }          function literal
          let f = fn(x) { x * 2 };    named function
          import "path";              import another file

        Multi-line input: unbalanced { ( [ continues on a '... ' prompt.

        Line editing (via readline):
          Left/Right    move cursor within the line
          Up/Down       navigate command history
          Ctrl-A/Ctrl-E start / end of line
          Ctrl-K        kill to end of line
          Ctrl-U        kill whole line
          Ctrl-W        kill previous word
          Ctrl-L        clear screen
          Ctrl-R        reverse history search
          Ctrl-D        exit (EOF)
          Tab           complete keywords and builtins
        History is saved to ~/.fel_history between sessions.

        Builtins:
          Core:    display, len, first, last, rest, push, type
          Convert: to_int, to_float, to_str, to_array, json_encode, json_decode
          String:  split, join, trim, upper, lower, contains
          Map:     keys, values
          Seq:     range, map, filter, reduce, sort, sort_by, reverse,
                   flatten, unique, zip, slice, concat, every, some, find
          Lazy:    lazy_range, count_from, take
          Math:    sqrt, pow, abs, floor, ceil, round, min, max,
                   log, sin, cos, tan
          IO:      read_file, write_file, readline

        HELP;
    }
}
