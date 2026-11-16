<?php

namespace App\Support;

/** Budget amounts are stored as integer cents to keep repeated additions exact. */
final class BudgetMoney
{
    public static function cents(string|int|float $amount): int
    {
        $parts = explode('.', (string) $amount, 2);
        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
