<?php

namespace App\Support;

class Csv
{
    /** Neutralise spreadsheet formula injection by prefixing a single quote. */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    public static function row(array $cells): array
    {
        return array_map(self::safe(...), $cells);
    }
}
