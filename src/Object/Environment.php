<?php
declare(strict_types=1);
namespace Fel\Object;

class Environment {
    /** @var array<string, FelObject> */
    private array $store = [];

    public function __construct(private readonly ?Environment $outer = null) {}

    public function get(string $name): ?FelObject {
        return $this->store[$name] ?? $this->outer?->get($name);
    }

    public function set(string $name, FelObject $val): FelObject {
        $this->store[$name] = $val;
        return $val;
    }

    public function assign(string $name, FelObject $val): bool {
        if (array_key_exists($name, $this->store)) {
            $this->store[$name] = $val;
            return true;
        }
        return $this->outer?->assign($name, $val) ?? false;
    }

    public static function enclosed(Environment $outer): self {
        return new self($outer);
    }
}
