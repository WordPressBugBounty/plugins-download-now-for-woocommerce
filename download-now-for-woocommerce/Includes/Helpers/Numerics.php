<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Helpers;

class Numerics
{
    public static function absInt($number): int
    {
        $number = (int) $number;
        return ($number < 0 ? $number * -1 : $number);
    }
}
