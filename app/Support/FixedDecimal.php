<?php

namespace App\Support;

use InvalidArgumentException;

final class FixedDecimal
{
    public static function toScaled(string|int|float $value, int $scale, bool $signed = false): int
    {
        $value = trim((string) $value);
        $pattern = $signed ? '/^-?\d+(?:\.\d{1,'.$scale.'})?$/' : '/^\d+(?:\.\d{1,'.$scale.'})?$/';
        if (! preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Nilai desimal tidak valid.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen($whole) > 12) {
            throw new InvalidArgumentException('Nilai desimal melewati batas.');
        }
        $scaled = ((int) $whole * (10 ** $scale)) + (int) str_pad($fraction, $scale, '0');

        return $negative ? -$scaled : $scaled;
    }

    public static function fromScaled(int $value, int $scale): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $base = 10 ** $scale;

        return ($negative ? '-' : '').intdiv($value, $base).'.'.str_pad((string) ($value % $base), $scale, '0', STR_PAD_LEFT);
    }

    public static function quantity(string|int|float $value): int { return self::toScaled($value, 3); }

    public static function quantityText(int $milliunits): string { return self::fromScaled($milliunits, 3); }

    public static function money(string|int|float $value): int { return self::toScaled($value, 2); }

    public static function moneyText(int $cents): string { return self::fromScaled($cents, 2); }
}
