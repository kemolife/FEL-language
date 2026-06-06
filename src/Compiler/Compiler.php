<?php
declare(strict_types=1);
namespace Fel\Compiler;

use Fel\Parser\ParserFactory;

final class Compiler {
    public function compileFile(string $source, string $moduleName = 'fel_output'): string {
        $parser  = ParserFactory::fromSource($source);
        $program = $parser->parseProgram();

        if ($errors = $parser->errors()) {
            throw new \RuntimeException("Parse errors:\n" . implode("\n", $errors));
        }

        $codegen = new CodeGen($moduleName);
        $module  = $codegen->compile($program);
        return $module->emit();
    }
}
