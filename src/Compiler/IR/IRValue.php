<?php
declare(strict_types=1);
namespace Fel\Compiler\IR;

/**
 * An SSA value in LLVM IR. Every instruction that produces a result
 * is an IRValue. Referenced by name in the emitted text.
 */
final class IRValue {
    private static int $counter = 0;

    public readonly string $name;

    public function __construct(public readonly IRType $type, ?string $name = null) {
        $this->name = $name ?? ('%' . self::$counter++);
    }

    public static function reset(): void { self::$counter = 0; }

    public function ref(): string { return $this->name; }
}
