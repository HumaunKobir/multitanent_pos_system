<?php

namespace App\Support;

use App\Exceptions\SteadfastCourierException;

class SteadfastPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            $digits = substr($digits, -11);
        }

        if (strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) !== 11 || ! preg_match('/^01[3-9]\d{8}$/', $digits)) {
            throw new SteadfastCourierException('Recipient phone must be an 11-digit Bangladeshi mobile number (e.g. 01712345678).');
        }

        return $digits;
    }
}
