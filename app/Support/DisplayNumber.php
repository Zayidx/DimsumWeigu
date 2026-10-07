<?php

namespace App\Support;

class DisplayNumber
{
    public static function format(
        int|float|string|null $number,
        int $decimals = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ',',
    ): string {
        $formatted = number_format((float) ($number ?? 0), $decimals, $decimalSeparator, $thousandsSeparator);

        if ($decimals === 0 || $decimalSeparator === '') {
            return $formatted;
        }

        $decimalPosition = strrpos($formatted, $decimalSeparator);

        if ($decimalPosition === false) {
            return $formatted;
        }

        $fraction = rtrim(substr($formatted, $decimalPosition + strlen($decimalSeparator)), '0');

        return $fraction === ''
            ? substr($formatted, 0, $decimalPosition)
            : substr($formatted, 0, $decimalPosition + strlen($decimalSeparator)).$fraction;
    }
}
