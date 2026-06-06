<?php
declare(strict_types=1);
namespace Fel\Compiler;

use Fel\Ast\Node;

final class TypeInference {
    public function infer(Node $node): string {
        return 'unknown'; // fully boxed — all values are FelVal*
    }
}
