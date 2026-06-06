<?php
declare(strict_types=1);
namespace Fel\Compiler\IR;

/**
 * LLVM IR type system — covers the types FEL needs.
 * Dynamic values are represented as i64 with tag + pointer boxing.
 */
enum IRType: string {
    case I1   = 'i1';       // bool
    case I8   = 'i8';       // byte
    case I32  = 'i32';      // int32
    case I64  = 'i64';      // int64 / tagged value
    case F64  = 'double';   // float64
    case PTR  = 'ptr';      // opaque pointer (LLVM 15+)
    case VOID = 'void';

    // FEL dynamic value: tagged union stored as { i8 tag, i64 payload }*
    // Tag values: 0=null 1=int 2=float 3=bool 4=string 5=array 6=hash 7=function
    case FEL_VAL = '%FelVal*';
    case FEL_ARR = '%FelArr*';
    case FEL_STR = '%FelStr*';

    public function ptr(): string { return $this->value . '*'; }
}
