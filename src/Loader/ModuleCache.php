<?php
declare(strict_types=1);
namespace Fel\Loader;

use Fel\Object\FelObject;

class ModuleCache {
    /** @var array<string, FelObject> */
    private array $cache = [];

    public function has(string $path): bool {
        return isset($this->cache[$path]);
    }

    public function get(string $path): ?FelObject {
        return $this->cache[$path] ?? null;
    }

    public function set(string $path, FelObject $module): void {
        $this->cache[$path] = $module;
    }
}
