<?php
declare(strict_types=1);

// All money is stored as an integer number of copper pieces.
// 1 gp = 10 sp = 100 cp.

const CP_PER_GP = 100;
const CP_PER_SP = 10;

/** 1234 -> "12 gp 3 sp 4 cp". */
function coins(int $cp): string
{
    if ($cp === 0) {
        return '0 gp';
    }
    $neg = $cp < 0;
    $cp = abs($cp);
    $parts = [];
    $gp = intdiv($cp, CP_PER_GP);
    $sp = intdiv($cp % CP_PER_GP, CP_PER_SP);
    $c  = $cp % CP_PER_SP;
    if ($gp) $parts[] = number_format($gp) . ' gp';
    if ($sp) $parts[] = $sp . ' sp';
    if ($c)  $parts[] = $c . ' cp';
    return ($neg ? '−' : '') . implode(' ', $parts);
}

/** Split copper into ['gp' => .., 'sp' => .., 'cp' => ..]. */
function coin_parts(int $cp): array
{
    return [
        'gp' => intdiv($cp, CP_PER_GP),
        'sp' => intdiv($cp % CP_PER_GP, CP_PER_SP),
        'cp' => $cp % CP_PER_SP,
    ];
}

function coins_from_parts(int $gp, int $sp, int $cp): int
{
    return max(0, $gp) * CP_PER_GP + max(0, $sp) * CP_PER_SP + max(0, $cp);
}

/**
 * Parse a price typed by the DM: "15 gp", "5sp", "1 gp 5 sp", "2.5 gp", or a bare number (gp).
 * Returns null when the text can't be understood, so callers can fall back to a default.
 */
function parse_price(string $text): ?int
{
    $text = strtolower(trim($text));
    if ($text === '') {
        return null;
    }
    if (is_numeric($text)) {
        return (int)round((float)$text * CP_PER_GP);
    }
    if (!preg_match_all('/(\d+(?:\.\d+)?)\s*(gp|sp|cp|g|s|c)\b/', $text, $m, PREG_SET_ORDER)) {
        return null;
    }
    $total = 0.0;
    foreach ($m as [, $n, $unit]) {
        $total += (float)$n * match ($unit[0]) {
            'g' => CP_PER_GP,
            's' => CP_PER_SP,
            default => 1,
        };
    }
    return (int)round($total);
}
