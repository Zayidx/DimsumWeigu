<?php

namespace App\Support;

final class CsvCell
{
    public static function safe(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        return preg_match('/^[\t\r =+\-@]/u', $value) ? "'".$value : $value;
    }
}
