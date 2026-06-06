<?php
declare(strict_types=1);
namespace Fel\Package;

use Fel\Evaluator\Evaluator;
use Fel\Object\{Environment, FelObject};
use Fel\Object\Type\{
    BuiltinObject, NullObject, StringObject, IntegerObject, FloatObject,
    BooleanObject, ArrayObject, ErrorObject,
};

final class PackageLoader {
    private string $projectPackagesDir;
    private string $globalPackagesDir;

    public function __construct(string $projectRoot) {
        $this->projectPackagesDir = $projectRoot . '/packages';
        $this->globalPackagesDir  = (getenv('HOME') ?: '/tmp') . '/.fel/packages';
    }

    /**
     * Find the directory for a package name.
     * Returns null if not found in project or global cache.
     */
    public function findPackageDir(string $name): ?string {
        $projectPath = $this->projectPackagesDir . '/' . $name;
        if (is_dir($projectPath)) {
            return $projectPath;
        }

        $globalPath = $this->globalPackagesDir . '/' . $name;
        if (is_dir($globalPath)) {
            return $globalPath;
        }

        return null;
    }

    /**
     * Load a package: register native.php functions into the given env,
     * then return the path to index.fel for the Importer to evaluate.
     * Returns null if no index.fel found.
     */
    public function preparePackage(string $packageDir, Evaluator $evaluator, Environment $env): ?string {
        // Load native.php if present
        $nativePath = $packageDir . '/native.php';
        if (file_exists($nativePath)) {
            $natives = require $nativePath;
            if (is_array($natives)) {
                foreach ($natives as $name => $callable) {
                    $env->set($name, new BuiltinObject(
                        function (FelObject ...$args) use ($callable): FelObject {
                            $phpArgs = array_map(fn($a) => $this->felToPhp($a), $args);
                            $result  = $callable(...$phpArgs);
                            return $this->phpToFel($result);
                        }
                    ));
                }
            }
        }

        $indexPath = $packageDir . '/index.fel';
        return file_exists($indexPath) ? $indexPath : null;
    }

    private function felToPhp(FelObject $obj): mixed
    {
        return match (true) {
            $obj instanceof IntegerObject => $obj->value,
            $obj instanceof FloatObject   => $obj->value,
            $obj instanceof StringObject  => $obj->value,
            $obj instanceof BooleanObject => $obj->value,
            $obj instanceof NullObject    => null,
            $obj instanceof ArrayObject   => array_map(fn($e) => $this->felToPhp($e), $obj->elements),
            default                       => $obj->inspect(),
        };
    }

    private function phpToFel(mixed $value): FelObject
    {
        return match (true) {
            is_int($value)    => new IntegerObject($value),
            is_float($value)  => new FloatObject($value),
            is_string($value) => new StringObject($value),
            is_bool($value)   => new BooleanObject($value),
            is_null($value)   => new NullObject(),
            is_array($value) && array_is_list($value) => new ArrayObject(
                array_map(fn($v) => $this->phpToFel($v), $value)
            ),
            default => new NullObject(),
        };
    }
}
