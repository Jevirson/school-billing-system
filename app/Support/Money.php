<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        $amount = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException('Money values must have no more than two decimal places.');
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $absoluteCents = abs($cents);
        $amount = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $cents < 0 ? '-'.$amount : $amount;
    }
}
