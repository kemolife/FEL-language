<?php
declare(strict_types=1);
namespace Fel\Object\Type;
use Fel\Object\{FelObject, ObjectType};
use Fel\Object\Environment;

final class FunctionObject implements FelObject {
    /** @param string[] $params */
    public function __construct(
        public readonly array       $params,
        public readonly \Closure    $body,
        public readonly string      $bodySrc,
        public readonly Environment $env,
    ) {}
    public function type(): ObjectType { return ObjectType::FUNCTION; }
    public function inspect(): string {
        $params = implode(', ', $this->params);
        return "fn({$params}) {\n{$this->bodySrc}\n}";
    }
}
