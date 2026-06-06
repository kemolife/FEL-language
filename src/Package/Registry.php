<?php
declare(strict_types=1);
namespace Fel\Package;

final class Registry {
    private const BUNDLED = [
        'http' => ['version' => '1.0.0', 'source' => 'bundled'],
        'date' => ['version' => '1.0.0', 'source' => 'bundled'],
    ];

    public function __construct(
        private readonly string $packagesDir,
    ) {}

    /**
     * Look up a package. Returns ['version' => ..., 'source' => ...] or null.
     */
    public function find(string $name): ?array {
        // Check local registry file first
        $registryFile = $this->packagesDir . '/.registry.json';
        if (file_exists($registryFile)) {
            $data = json_decode(file_get_contents($registryFile), true);
            if (isset($data[$name])) return $data[$name];
        }

        // Check bundled packages (shipped with FEL)
        return self::BUNDLED[$name] ?? null;
    }

    /**
     * List all known packages (local + bundled).
     * @return array<string, array{version: string, source: string}>
     */
    public function all(): array {
        $packages = self::BUNDLED;
        $registryFile = $this->packagesDir . '/.registry.json';
        if (file_exists($registryFile)) {
            $data = json_decode(file_get_contents($registryFile), true);
            $packages = array_merge($packages, $data ?? []);
        }
        return $packages;
    }

    /**
     * Install a package. For bundled packages, they're already in packages/ dir
     * (shipped with FEL). For remote packages, stub: just print a note.
     * Returns true on success.
     */
    public function install(string $name, string $version, string $projectRoot): bool {
        $info = $this->find($name);
        if ($info === null) {
            return false;
        }

        $targetDir = $projectRoot . '/packages/' . $name;

        if ($info['source'] === 'bundled') {
            // Bundled packages are shipped with FEL in packages/ directory
            // They're already there — nothing to copy
            if (!is_dir($targetDir)) {
                // Package shipped but not present — would download in real impl
                echo "  note: bundled package '{$name}' not found locally; skipping\n";
            }
            return true;
        }

        // Remote source — stub
        echo "  note: remote package installation not yet implemented for '{$name}'\n";
        return false;
    }
}
