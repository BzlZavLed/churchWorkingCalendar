<?php

namespace App\Services;

use App\Models\Church;

class MemberDateFormat
{
    public static function forChurch(int $church): string
    {
        return Church::whereKey($church)->value('member_date_format') ?? 'mdy';
    }

    public static function normalize(mixed $value, string $format, bool $fromImport = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
            [, $year, $month, $day] = $parts;
            if ($fromImport && $format === 'ydm') {
                [$month, $day] = [$day, $month];
            }
        } elseif (preg_match('~^(\d{1,2})([/-])(\d{1,2})\2(\d{4})$~D', $value, $parts)) {
            [, $first, , $second, $year] = $parts;
            [$month, $day] = $format === 'dmy' ? [$second, $first] : [$first, $second];
        } else {
            return null;
        }

        return checkdate((int) $month, (int) $day, (int) $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : null;
    }
}
