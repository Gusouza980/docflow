<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Convert a reais input (pt-BR or plain decimal) to integer cents.
     *
     * Empty values become null. Whole numbers are treated as reais (1250 → 125000),
     * never as pre-converted cents.
     *
     * @throws InvalidArgumentException
     */
    public static function toCents(null|string|int|float $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = self::normalizeInput($value);

            if ($normalized === null) {
                return null;
            }

            return self::reaisStringToCents($normalized);
        }

        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('Money value must be non-negative.');
            }

            return $value * 100;
        }

        if (! is_finite($value) || $value < 0) {
            throw new InvalidArgumentException('Money value must be a finite non-negative number.');
        }

        return self::reaisFloatToCents($value);
    }

    public static function tryToCents(null|string|int|float $value): ?int
    {
        try {
            return self::toCents($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function normalizeInput(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $withoutSymbol = preg_replace('/^\s*R\$\s*/iu', '', $trimmed) ?? $trimmed;
        $normalized = trim(str_replace(' ', '', $withoutSymbol));

        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }

    private static function reaisStringToCents(string $value): int
    {
        if (! preg_match('/^-?\d{1,3}([.,]\d{3})*([.,]\d+)?$|^-?\d+([.,]\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money format.');
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($hasComma) {
            $parts = explode(',', $value);

            if (count($parts) !== 2 || strlen($parts[1]) > 2) {
                throw new InvalidArgumentException('Invalid money format.');
            }

            $value = $parts[0].'.'.$parts[1];
        } elseif ($hasDot) {
            $parts = explode('.', $value);

            if (count($parts) > 2) {
                $value = str_replace('.', '', $value);
            } elseif (count($parts) === 2 && strlen($parts[1]) === 3 && strlen($parts[0]) <= 3) {
                // Ambiguous "1.250" — treat as thousand separator in pt-BR (R$ 1.250).
                $value = str_replace('.', '', $value);
            }
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Invalid money format.');
        }

        return self::reaisFloatToCents((float) $value);
    }

    private static function reaisFloatToCents(float $reais): int
    {
        if ($reais < 0) {
            throw new InvalidArgumentException('Money value must be non-negative.');
        }

        $scaled = round($reais * 100, 4);

        if (abs($scaled - round($scaled)) > 0.0001) {
            throw new InvalidArgumentException('Money value cannot have more than 2 decimal places.');
        }

        return (int) round($scaled);
    }
}
