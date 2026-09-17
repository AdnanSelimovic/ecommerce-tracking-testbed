<?php

namespace App\Support;

/**
 * Minimal integer-minor-unit money helpers.
 *
 * Monetary values never touch floating-point arithmetic; they are only
 * converted to a decimal string at the presentation boundary.
 */
final class Money
{
    public static function toDecimalString(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $minor, ?string $currency = null): string
    {
        $currency ??= config('testbed.currency');

        return self::toDecimalString($minor).' '.$currency;
    }
}
