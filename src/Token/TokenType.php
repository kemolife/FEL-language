<?php
declare(strict_types=1);
namespace Fel\Token;

enum TokenType: string {
    case ILLEGAL  = 'ILLEGAL';
    case EOF      = 'EOF';
    // literals
    case IDENT    = 'IDENT';
    case INT      = 'INT';
    case FLOAT    = 'FLOAT';
    case STRING   = 'STRING';
    // operators
    case ASSIGN   = '=';
    case PLUS     = '+';
    case MINUS    = '-';
    case BANG     = '!';
    case ASTERISK = '*';
    case SLASH    = '/';
    case PERCENT  = '%';
    case LT       = '<';
    case GT       = '>';
    case LT_EQ    = '<=';
    case GT_EQ    = '>=';
    case AND      = '&&';
    case OR       = '||';
    // delimiters
    case COMMA     = ',';
    case SEMICOLON = ';';
    case LPAREN    = '(';
    case RPAREN    = ')';
    case LBRACE    = '{';
    case RBRACE    = '}';
    case LBRACKET  = '[';
    case RBRACKET  = ']';
    case COLON     = ':';
    case DOT       = '.';
    // keywords
    case FUNCTION = 'FUNCTION';
    case LET      = 'LET';
    case TRUE     = 'TRUE';
    case FALSE    = 'FALSE';
    case IF       = 'IF';
    case ELSE     = 'ELSE';
    case RETURN   = 'RETURN';
    case WHILE    = 'WHILE';
    case FOR      = 'FOR';
    case IN       = 'IN';
    case BREAK    = 'BREAK';
    case CONTINUE = 'CONTINUE';
    case IMPORT   = 'IMPORT';
    case NULL     = 'NULL';
    // equality
    case EQ     = '==';
    case NOT_EQ = '!=';
}
