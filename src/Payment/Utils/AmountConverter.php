<?php

declare(strict_types=1);

namespace Plugs\Payment\Utils;

/**
 * AmountConverter
 * 
 * Utility for converting decimal amounts to subunits (cents, kobo, etc.)
 * based on the currency code.
 */
class AmountConverter
{
    /**
     * Currencies that have zero decimals.
     * 
     * @var array
     */
    protected static array $zeroDecimalCurrencies = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 
        'PYG', 'RWF', 'UGX', 'VUV', 'VND', 'XAF', 'XBA', 'XBB', 
        'XBC', 'XBD', 'XOF', 'XPF'
    ];

    /**
     * Convert a decimal amount to subunits.
     * 
     * @param float|int|string $amount
     * @param string $currency
     * @return int
     */
    public static function toSubunits($amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $amount = (float) $amount;

        if (in_array($currency, self::$zeroDecimalCurrencies)) {
            return (int) round($amount);
        }

        // Most currencies have 2 decimal places (USD, NGN, EUR, etc.)
        return (int) round($amount * 100);
    }

    /**
     * Convert a decimal amount to a formatted decimal string.
     * 
     * @param float|int|string $amount
     * @param string $currency
     * @return string
     */
    public static function toDecimal($amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $amount = (float) $amount;

        if (in_array($currency, self::$zeroDecimalCurrencies)) {
            return (string) round($amount);
        }

        return number_format($amount, 2, '.', '');
    }

    /**
     * Convert subunits back to decimal amount.
     * 
     * @param int $subunits
     * @param string $currency
     * @return float
     */
    public static function fromSubunits(int $subunits, string $currency): float
    {
        $currency = strtoupper($currency);

        if (in_array($currency, self::$zeroDecimalCurrencies)) {
            return (float) $subunits;
        }

        return (float) ($subunits / 100);
    }
}
