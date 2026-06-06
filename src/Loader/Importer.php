<?php
declare(strict_types=1);
namespace Fel\Loader;

use Fel\Evaluator\Evaluator;
use Fel\Object\{Environment, FelObject};
use Fel\Object\Type\{ErrorObject, NullObject};
use Fel\Package\PackageLoader;
use Fel\Parser\ParserFactory;

final class Importer {
    private readonly string        $stdlibDir;
    private readonly PackageLoader $packageLoader;

    public function __construct(
        private readonly ModuleCache $cache = new ModuleCache(),
        ?string $projectRoot = null,
    ) {
        $this->stdlibDir     = dirname(__DIR__, 2) . '/stdlib';
        $this->packageLoader = new PackageLoader($projectRoot ?? getcwd());
    }

    public function load(string $importPath, Evaluator $evaluator): FelObject {
        if ($this->cache->has($importPath)) {
            return $this->cache->get($importPath) ?? new NullObject();
        }

        // Circular import guard
        $sentinel = new ErrorObject("import: circular dependency on \"{$importPath}\"");
        $this->cache->set($importPath, $sentinel);

        // Bare name (no slashes, not stdlib/) → treat as package
        if (!str_starts_with($importPath, 'stdlib/') && !str_contains($importPath, '/')) {
            return $this->loadPackage($importPath, $evaluator);
        }

        $filePath = $this->resolve($importPath);
        if ($filePath === null) {
            return new ErrorObject("import: cannot find module \"{$importPath}\"");
        }

        $source = file_get_contents($filePath);
        if ($source === false) {
            return new ErrorObject("import: cannot read \"{$filePath}\"");
        }

        $parser  = ParserFactory::fromSource($source);
        $program = $parser->parseProgram();

        if ($parser->errors()) {
            $errors = implode('; ', $parser->errors());
            return new ErrorObject("import \"{$importPath}\": parse errors: {$errors}");
        }

        $moduleEnv = new Environment();
        $result    = $evaluator->eval($program, $moduleEnv);

        if ($result instanceof ErrorObject) {
            return $result;
        }

        $this->cache->set($importPath, $result);
        return $result;
    }

    private function loadPackage(string $name, Evaluator $evaluator): FelObject {
        $packageDir = $this->packageLoader->findPackageDir($name);
        if ($packageDir === null) {
            return new ErrorObject("import: package not found: \"{$name}\" (run: fel add {$name})");
        }

        $moduleEnv = new Environment();
        $indexPath = $this->packageLoader->preparePackage($packageDir, $evaluator, $moduleEnv);

        if ($indexPath === null) {
            return new ErrorObject("import: package \"{$name}\" has no index.fel");
        }

        $source = file_get_contents($indexPath);
        if ($source === false) {
            return new ErrorObject("import: cannot read package index for \"{$name}\"");
        }

        $parser  = ParserFactory::fromSource($source);
        $program = $parser->parseProgram();

        if ($parser->errors()) {
            $errors = implode('; ', $parser->errors());
            return new ErrorObject("package \"{$name}\": parse errors: {$errors}");
        }

        $result = $evaluator->eval($program, $moduleEnv);

        $this->cache->set($name, $result);
        return $result;
    }

    private function resolve(string $importPath): ?string {
        if (str_starts_with($importPath, 'stdlib/')) {
            $name = substr($importPath, strlen('stdlib/'));
            $path = $this->stdlibDir . '/' . $name . '.fel';
            return file_exists($path) ? $path : null;
        }
        $path = $importPath . '.fel';
        return file_exists($path) ? $path : null;
    }
}
