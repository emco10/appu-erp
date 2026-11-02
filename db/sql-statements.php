<?php

function appuSplitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $hasSql = false;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        $next = $sql[$index + 1] ?? null;

        if ($lineComment) {
            $buffer .= $character;
            if ($character === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            $buffer .= $character;
            if ($character === '*' && $next === '/') {
                $buffer .= $sql[++$index];
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== null) {
            $buffer .= $character;
            if ($character === '\\' && $index + 1 < $length) {
                $buffer .= $sql[++$index];
                continue;
            }
            if ($character === $quote) {
                if ($next === $quote) {
                    $buffer .= $sql[++$index];
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
            $buffer .= '--';
            $index++;
            $lineComment = true;
            continue;
        }
        if ($character === '#') {
            $buffer .= $character;
            $lineComment = true;
            continue;
        }
        if ($character === '/' && $next === '*') {
            $buffer .= '/*';
            $index++;
            $blockComment = true;
            continue;
        }
        if ($character === "'" || $character === '"' || $character === '`') {
            $buffer .= $character;
            $quote = $character;
            $hasSql = true;
            continue;
        }
        if ($character === ';') {
            if ($hasSql) {
                $statements[] = trim($buffer);
            }
            $buffer = '';
            $hasSql = false;
            continue;
        }

        $buffer .= $character;
        if (!ctype_space($character)) {
            $hasSql = true;
        }
    }

    if ($quote !== null || $blockComment) {
        throw new RuntimeException('Migration contains an unterminated SQL string or comment.');
    }
    if ($hasSql) {
        $statements[] = trim($buffer);
    }

    return $statements;
}
