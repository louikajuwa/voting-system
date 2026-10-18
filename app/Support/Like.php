<?php

namespace App\Support;

class Like
{
    /** Escape LIKE wildcards (% _ \) so user input is matched literally. */
    public static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
