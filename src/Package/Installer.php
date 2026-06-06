<?php
declare(strict_types=1);
namespace Fel\Package;

final class Installer {
    private readonly Registry $registry;

    public function __construct(private readonly string $projectRoot) {
        $packagesDir    = $projectRoot . '/packages';
        $this->registry = new Registry($packagesDir);
    }

    /**
     * Add a package: look up in registry, install, update manifest + lockfile.
     */
    public function add(string $name): void {
        $manifestPath = $this->projectRoot . '/fel.toml';
        $lockPath     = $this->projectRoot . '/fel.lock';

        $manifest = Manifest::fromFile($manifestPath);
        $lock     = LockFile::fromFile($lockPath);

        if ($manifest->hasDependency($name)) {
            echo "Package '{$name}' already in fel.toml.\n";
            return;
        }

        $info = $this->registry->find($name);
        if ($info === null) {
            echo "Error: package '{$name}' not found in registry.\n";
            return;
        }

        $version = $info['version'];

        $ok = $this->registry->install($name, $version, $this->projectRoot);
        if (!$ok) {
            echo "Error: could not install '{$name}'.\n";
            return;
        }

        $manifest = $manifest->withDependency($name, $version);
        file_put_contents($manifestPath, $manifest->toToml());

        $lock = $lock->with($name, $version, $info['source']);
        $lock->save($lockPath);

        echo "Added '{$name}' {$version} to fel.toml.\n";
    }

    /**
     * Remove a package: remove from manifest, lockfile, and packages/ dir.
     */
    public function remove(string $name): void {
        $manifestPath = $this->projectRoot . '/fel.toml';
        $lockPath     = $this->projectRoot . '/fel.lock';

        $manifest = Manifest::fromFile($manifestPath);
        $lock     = LockFile::fromFile($lockPath);

        if (!$manifest->hasDependency($name)) {
            echo "Package '{$name}' not in fel.toml.\n";
            return;
        }

        $manifest = $manifest->withoutDependency($name);
        file_put_contents($manifestPath, $manifest->toToml());

        $lock = $lock->without($name);
        $lock->save($lockPath);

        echo "Removed '{$name}' from fel.toml.\n";
    }

    /**
     * Install all dependencies from fel.toml.
     */
    public function installAll(): void {
        $manifestPath = $this->projectRoot . '/fel.toml';
        $lockPath     = $this->projectRoot . '/fel.lock';

        $manifest = Manifest::fromFile($manifestPath);
        $lock     = LockFile::fromFile($lockPath);

        if (empty($manifest->dependencies)) {
            echo "No dependencies in fel.toml.\n";
            return;
        }

        foreach ($manifest->dependencies as $name => $version) {
            echo "Installing {$name} {$version}...\n";
            $info = $this->registry->find($name);
            if ($info === null) {
                echo "  error: '{$name}' not found in registry\n";
                continue;
            }
            $ok = $this->registry->install($name, $info['version'], $this->projectRoot);
            if ($ok && !$lock->has($name)) {
                $lock = $lock->with($name, $info['version'], $info['source']);
            }
            echo $ok ? "  ok\n" : "  failed\n";
        }

        $lock->save($lockPath);
    }

    /**
     * List installed packages.
     */
    public function listInstalled(): void {
        $manifestPath = $this->projectRoot . '/fel.toml';
        $manifest = Manifest::fromFile($manifestPath);
        $lock     = LockFile::fromFile($this->projectRoot . '/fel.lock');

        if (empty($manifest->dependencies)) {
            echo "No packages installed.\n";
            return;
        }

        foreach ($manifest->dependencies as $name => $constraint) {
            $installed = $lock->version($name);
            echo "  {$name} {$constraint}" . ($installed ? " (installed: {$installed})" : " (not installed)") . "\n";
        }
    }
}
