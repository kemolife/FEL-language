<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Fel\Parser\ParserFactory;

$src = $argv[1] ?? null;
if ($src === null) {
    fwrite(STDERR, "usage: php bin/ast-dump.php '<code>'   OR   php bin/ast-dump.php file.fel\n");
    exit(1);
}
if (str_ends_with($src, '.fel') && file_exists($src)) {
    $src = file_get_contents($src);
}

$parser  = ParserFactory::fromSource($src);
$program = $parser->parseProgram();

$errs = $parser->errors();
if ($errs) {
    fwrite(STDERR, "Parser errors:\n");
    foreach ($errs as $e) fwrite(STDERR, "  - $e\n");
}

function dump(mixed $node, string $indent = '', bool $last = true): void {
    $branch = $indent === '' ? '' : ($last ? '└─ ' : '├─ ');
    $childIndent = $indent . ($indent === '' ? '' : ($last ? '   ' : '│  '));

    if ($node instanceof \Fel\Ast\Node) {
        $cls   = (new ReflectionClass($node))->getShortName();
        $props = (new ReflectionObject($node))->getProperties();
        // gather scalar token literal for context
        echo "{$indent}{$branch}\033[36m{$cls}\033[0m\n";
        $kids = [];
        foreach ($props as $p) {
            $v = $p->getValue($node);
            if ($v instanceof \Fel\Token\Token) continue; // skip token noise
            $kids[$p->getName()] = $v;
        }
        $n = count($kids); $i = 0;
        foreach ($kids as $name => $v) {
            $i++;
            $isLast = $i === $n;
            $b = $isLast ? '└─ ' : '├─ ';
            $ci = $childIndent . ($isLast ? '   ' : '│  ');
            if ($v instanceof \Fel\Ast\Node) {
                echo "{$childIndent}{$b}\033[33m{$name}:\033[0m\n";
                dump($v, $ci, true);
            } elseif (is_array($v)) {
                echo "{$childIndent}{$b}\033[33m{$name}[]:\033[0m\n";
                $m = count($v); $j = 0;
                foreach ($v as $item) { $j++; dump($item, $ci, $j === $m); }
            } else {
                $disp = is_bool($v) ? ($v ? 'true' : 'false') : var_export($v, true);
                echo "{$childIndent}{$b}\033[33m{$name}:\033[0m {$disp}\n";
            }
        }
    } else {
        $disp = is_bool($node) ? ($node ? 'true' : 'false') : var_export($node, true);
        echo "{$indent}{$branch}{$disp}\n";
    }
}

echo "\033[1mAST\033[0m  (string form: " . trim($program->string()) . ")\n";
dump($program);
