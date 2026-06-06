<?php
declare(strict_types=1);
namespace Fel\Lexer;

use Fel\Token\{Token, TokenType, Keywords};

class Lexer {
    private int    $position     = 0;
    private int    $readPosition = 0;
    private string $ch           = '';
    private int    $line         = 1;
    private int    $col          = 0;

    public function __construct(private readonly string $input) {
        $this->readChar();
    }

    public function nextToken(): Token {
        $this->skipWhitespaceAndComments();

        $line = $this->line;
        $col  = $this->col;

        $tok = match($this->ch) {
            '"'  => new Token(TokenType::STRING, $this->readString(), $line, $col),
            '='  => $this->peekChar() === '='
                        ? $this->twoCharToken(TokenType::EQ, $line, $col)
                        : new Token(TokenType::ASSIGN, '=', $line, $col),
            '!'  => $this->peekChar() === '='
                        ? $this->twoCharToken(TokenType::NOT_EQ, $line, $col)
                        : new Token(TokenType::BANG, '!', $line, $col),
            '<'  => $this->peekChar() === '='
                        ? $this->twoCharToken(TokenType::LT_EQ, $line, $col)
                        : new Token(TokenType::LT, '<', $line, $col),
            '>'  => $this->peekChar() === '='
                        ? $this->twoCharToken(TokenType::GT_EQ, $line, $col)
                        : new Token(TokenType::GT, '>', $line, $col),
            '&'  => $this->peekChar() === '&'
                        ? $this->twoCharToken(TokenType::AND, $line, $col)
                        : new Token(TokenType::ILLEGAL, '&', $line, $col),
            '|'  => $this->peekChar() === '|'
                        ? $this->twoCharToken(TokenType::OR, $line, $col)
                        : new Token(TokenType::ILLEGAL, '|', $line, $col),
            ';'  => new Token(TokenType::SEMICOLON, ';', $line, $col),
            '('  => new Token(TokenType::LPAREN,    '(', $line, $col),
            ')'  => new Token(TokenType::RPAREN,    ')', $line, $col),
            ','  => new Token(TokenType::COMMA,      ',', $line, $col),
            '+'  => new Token(TokenType::PLUS,       '+', $line, $col),
            '{'  => new Token(TokenType::LBRACE,     '{', $line, $col),
            '}'  => new Token(TokenType::RBRACE,     '}', $line, $col),
            '-'  => new Token(TokenType::MINUS,      '-', $line, $col),
            '['  => new Token(TokenType::LBRACKET,   '[', $line, $col),
            ']'  => new Token(TokenType::RBRACKET,   ']', $line, $col),
            ':'  => new Token(TokenType::COLON,      ':', $line, $col),
            '.'  => new Token(TokenType::DOT,        '.', $line, $col),
            '*'  => new Token(TokenType::ASTERISK,   '*', $line, $col),
            '/'  => new Token(TokenType::SLASH,      '/', $line, $col),
            '%'  => new Token(TokenType::PERCENT,    '%', $line, $col),
            ''   => new Token(TokenType::EOF,        '',  $line, $col),
            default => $this->readIdentifierOrNumber($line, $col),
        };

        // twoCharToken already calls readChar twice; for single-char tokens advance once.
        // IDENT, INT, FLOAT, STRING, keyword tokens, and two-char tokens all already
        // leave the lexer positioned PAST their last character, so no extra advance needed.
        if (!in_array($tok->type, [
            TokenType::IDENT, TokenType::INT, TokenType::FLOAT, TokenType::STRING,
            TokenType::EQ, TokenType::NOT_EQ, TokenType::LT_EQ, TokenType::GT_EQ,
            TokenType::AND, TokenType::OR,
            // keyword tokens come from readIdentifier() and are already past their last char
            TokenType::FUNCTION, TokenType::LET, TokenType::TRUE, TokenType::FALSE,
            TokenType::IF, TokenType::ELSE, TokenType::RETURN, TokenType::WHILE,
            TokenType::FOR, TokenType::IN, TokenType::BREAK, TokenType::CONTINUE,
            TokenType::IMPORT, TokenType::NULL,
        ])) {
            $this->readChar();
        }

        return $tok;
    }

    private function twoCharToken(TokenType $type, int $line, int $col): Token {
        $first = $this->ch;
        $this->readChar();
        $literal = $first . $this->ch;
        $this->readChar();
        return new Token($type, $literal, $line, $col);
    }

    private function readIdentifierOrNumber(int $line, int $col): Token {
        if ($this->isLetter($this->ch)) {
            $literal = $this->readIdentifier();
            $type    = Keywords::lookup($literal);
            return new Token($type, $literal, $line, $col);
        }
        if ($this->isDigit($this->ch)) {
            [$literal, $isFloat] = $this->readNumber();
            $type = $isFloat ? TokenType::FLOAT : TokenType::INT;
            return new Token($type, $literal, $line, $col);
        }
        $ch = $this->ch;
        $this->readChar();
        return new Token(TokenType::ILLEGAL, $ch, $line, $col);
    }

    private function readChar(): void {
        if ($this->readPosition >= strlen($this->input)) {
            $this->ch = '';
        } else {
            $this->ch = $this->input[$this->readPosition];
        }
        $this->position     = $this->readPosition;
        $this->readPosition++;
        if ($this->ch === "\n") {
            $this->line++;
            $this->col = 0;
        } else {
            $this->col++;
        }
    }

    private function peekChar(): string {
        if ($this->readPosition >= strlen($this->input)) {
            return '';
        }
        return $this->input[$this->readPosition];
    }

    private function skipWhitespaceAndComments(): void {
        while (true) {
            while ($this->ch === ' ' || $this->ch === "\t" || $this->ch === "\n" || $this->ch === "\r") {
                $this->readChar();
            }
            // single-line comment
            if ($this->ch === '/' && $this->peekChar() === '/') {
                while ($this->ch !== "\n" && $this->ch !== '') {
                    $this->readChar();
                }
                continue;
            }
            break;
        }
    }

    private function readIdentifier(): string {
        $start = $this->position;
        while ($this->isLetter($this->ch) || ($this->position > $start && $this->isDigit($this->ch))) {
            $this->readChar();
        }
        return substr($this->input, $start, $this->position - $start);
    }

    /** @return array{string, bool} [literal, isFloat] */
    private function readNumber(): array {
        $start = $this->position;
        while ($this->isDigit($this->ch)) {
            $this->readChar();
        }
        if ($this->ch === '.' && $this->isDigit($this->peekChar())) {
            $this->readChar();
            while ($this->isDigit($this->ch)) {
                $this->readChar();
            }
            return [substr($this->input, $start, $this->position - $start), true];
        }
        return [substr($this->input, $start, $this->position - $start), false];
    }

    private function readString(): string {
        $result = '';
        $this->readChar(); // skip opening "
        while ($this->ch !== '"' && $this->ch !== '') {
            if ($this->ch === '\\') {
                $this->readChar();
                $result .= match($this->ch) {
                    'n'  => "\n",
                    't'  => "\t",
                    '"'  => '"',
                    '\\' => '\\',
                    default => '\\' . $this->ch,
                };
            } else {
                $result .= $this->ch;
            }
            $this->readChar();
        }
        // ch is now '"', readChar() will happen in nextToken's single-char advance — but
        // STRING is in the excluded list so we must advance past closing quote here
        $this->readChar();
        return $result;
    }

    private function isLetter(string $ch): bool {
        return $ch !== '' && (ctype_alpha($ch) || $ch === '_');
    }

    private function isDigit(string $ch): bool {
        return $ch !== '' && ctype_digit($ch);
    }
}
