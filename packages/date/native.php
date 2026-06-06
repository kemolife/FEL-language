<?php
return [
    'date_now'    => fn(): int    => time(),
    'date_format' => fn(int $ts, string $fmt): string => date($fmt, $ts),
    'date_parse'  => fn(string $s): int => strtotime($s) ?: 0,
    'date_diff'   => fn(int $a, int $b): int => abs($b - $a),
    'date_add'    => fn(int $ts, int $seconds): int => $ts + $seconds,
];
