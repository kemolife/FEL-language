<?php
declare(strict_types=1);
namespace Fel\Evaluator;

use Fel\Object\FelObject;
use Fel\Object\Type\{NullObject, BooleanObject};

final class Values {
    public function __construct(
        private readonly NullObject    $null,
        private readonly BooleanObject $true,
        private readonly BooleanObject $false,
    ) {}

    public static function default(): self {
        return new self(new NullObject(), new BooleanObject(true), new BooleanObject(false));
    }

    public function null(): NullObject       { return $this->null; }
    public function true(): BooleanObject     { return $this->true; }
    public function false(): BooleanObject    { return $this->false; }
    public function bool(bool $v): BooleanObject { return $v ? $this->true : $this->false; }

    public function isTruthy(FelObject $o): bool {
        return match(true) {
            $o instanceof NullObject => false,
            $o === $this->true       => true,
            $o === $this->false      => false,
            default                  => true,
        };
    }
}
