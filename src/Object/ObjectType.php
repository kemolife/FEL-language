<?php
declare(strict_types=1);
namespace Fel\Object;

enum ObjectType: string {
    case INTEGER  = 'INTEGER';
    case FLOAT    = 'FLOAT';
    case STRING   = 'STRING';
    case BOOLEAN  = 'BOOLEAN';
    case NULL     = 'NULL';
    case RETURN   = 'RETURN_VALUE';
    case ERROR    = 'ERROR';
    case FUNCTION = 'FUNCTION';
    case BUILTIN  = 'BUILTIN';
    case ARRAY    = 'ARRAY';
    case HASH     = 'HASH';
    case GENERATOR = 'GENERATOR';
    case BREAK    = 'BREAK';
    case CONTINUE = 'CONTINUE';
    case STRUCT_TYPE = 'STRUCT_TYPE';
    case STRUCT   = 'STRUCT';
    case INTERFACE = 'INTERFACE';
}
