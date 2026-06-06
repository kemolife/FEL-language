<?php
declare(strict_types=1);
namespace Fel\Compiler\IR;

/**
 * A single LLVM IR function definition.
 */
class IRFunction {
    /** @var IRBuilder[] keyed by block label */
    private array $blocks   = [];
    private string $current = 'entry';

    /** @param array<string, IRType> $params name => type */
    public function __construct(
        public readonly string  $name,
        public readonly IRType  $returnType,
        public readonly array   $params = [],
        public readonly bool    $isInternal = true,
    ) {
        $this->blocks['entry'] = new IRBuilder();
    }

    public function builder(?string $block = null): IRBuilder {
        $b = $block ?? $this->current;
        if (!isset($this->blocks[$b])) {
            $this->blocks[$b] = new IRBuilder();
        }
        $this->current = $b;
        return $this->blocks[$b];
    }

    public function newBlock(string $label): IRBuilder {
        $this->blocks[$label] = new IRBuilder();
        return $this->blocks[$label];
    }

    public function switchTo(string $label): IRBuilder {
        $this->current = $label;
        return $this->builder($label);
    }

    public function emit(): string {
        $linkage  = $this->isInternal ? 'internal ' : '';
        $paramStr = implode(', ', array_map(
            fn($name, $type) => "{$type->value} %{$name}",
            array_keys($this->params),
            array_values($this->params)
        ));

        $lines   = [];
        $lines[] = "define {$linkage}{$this->returnType->value} @{$this->name}({$paramStr}) {";

        foreach ($this->blocks as $label => $builder) {
            // Always emit the label, including 'entry'. An unlabeled entry block
            // implicitly consumes SSA number %0, colliding with the first unnamed
            // temporary. A named block consumes no number, freeing %0 for use.
            $lines[] = "{$label}:";
            foreach ($builder->instructions() as $instr) {
                $lines[] = $instr;
            }
        }

        $lines[] = '}';
        return implode("\n", $lines);
    }
}
