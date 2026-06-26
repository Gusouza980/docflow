<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DisplayFormat
{
    public const DATE = 'd/m/Y';

    public const DATETIME = 'd/m/Y H:i';

    public static function date(CarbonInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format(self::DATE);
    }

    public static function dateTime(CarbonInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format(self::DATETIME);
    }
}
