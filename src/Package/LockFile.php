<?php
declare(strict_types=1);
namespace Fel\Package;

final class LockFile {
    private function __construct(
        /** @var array<string, array{version: string, source: string}> */
        public readonly array $packages,
    ) {}

    public static function empty(): self {
        return new self([]);
    }

    public static function fromFile(string $path): self {
        if (!file_exists($path)) return self::empty();
        $data = json_decode(file_get_contents($path), true);
        return new self($data['packages'] ?? []);
    }

    public function save(string $path): void {
        file_put_contents($path, json_encode(
            ['packages' => $this->packages],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n");
    }

    public function with(string $name, string $version, string $source = 'local'): self {
        $packages = $this->packages;
        $packages[$name] = ['version' => $version, 'source' => $source];
        return new self($packages);
    }

    public function without(string $name): self {
        $packages = $this->packages;
        unset($packages[$name]);
        return new self($packages);
    }

    public function has(string $name): bool {
        return isset($this->packages[$name]);
    }

    public function version(string $name): ?string {
        return $this->packages[$name]['version'] ?? null;
    }
}
