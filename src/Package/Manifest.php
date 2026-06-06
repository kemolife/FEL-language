<?php
declare(strict_types=1);
namespace Fel\Package;

final class Manifest {
    private function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        /** @var array<string, string> name => version constraint */
        public readonly array $dependencies,
    ) {}

    public static function fromFile(string $path): self {
        if (!file_exists($path)) {
            throw new \RuntimeException("fel.toml not found at {$path}");
        }
        return self::parse(file_get_contents($path));
    }

    public static function parse(string $toml): self {
        // Parse simple TOML: [section] headers + key = "value" lines
        // Support: string values only, # comments, blank lines
        $sections = [];
        $current  = null;

        foreach (explode("\n", $toml) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (preg_match('/^\[([a-zA-Z0-9_]+)\]$/', $line, $m)) {
                $current = $m[1];
                $sections[$current] ??= [];
                continue;
            }

            if ($current !== null && preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"$/', $line, $m)) {
                $sections[$current][$m[1]] = $m[2];
            }
        }

        return new self(
            name:         $sections['package']['name']        ?? 'unnamed',
            version:      $sections['package']['version']     ?? '0.0.0',
            description:  $sections['package']['description'] ?? '',
            dependencies: $sections['dependencies']           ?? [],
        );
    }

    public function toToml(): string {
        $lines = [];
        $lines[] = '[package]';
        $lines[] = 'name = "' . $this->name . '"';
        $lines[] = 'version = "' . $this->version . '"';
        if ($this->description !== '') {
            $lines[] = 'description = "' . $this->description . '"';
        }
        if ($this->dependencies) {
            $lines[] = '';
            $lines[] = '[dependencies]';
            foreach ($this->dependencies as $name => $ver) {
                $lines[] = $name . ' = "' . $ver . '"';
            }
        }
        return implode("\n", $lines) . "\n";
    }

    public function withDependency(string $name, string $version): self {
        $deps = $this->dependencies;
        $deps[$name] = $version;
        return new self($this->name, $this->version, $this->description, $deps);
    }

    public function withoutDependency(string $name): self {
        $deps = $this->dependencies;
        unset($deps[$name]);
        return new self($this->name, $this->version, $this->description, $deps);
    }

    public function hasDependency(string $name): bool {
        return isset($this->dependencies[$name]);
    }
}
