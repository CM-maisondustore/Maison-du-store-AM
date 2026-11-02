<?php
namespace App\Services\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Decimal quantities and integer XOF, without binary floating point arithmetic. */
final class QuoteValidator
{
    public static function decimal(mixed $value, string $field, bool $positive = false): BigDecimal
    {
        if (!is_int($value) && !is_string($value) && !is_float($value)) self::fail($field);
        $text = str_replace(',', '.', (string) $value);
        if (!preg_match('/^\d{1,13}(?:\.\d{1,6})?$/D', $text)) self::fail($field);
        $number = BigDecimal::of($text);
        if ($number->isGreaterThan('1000000000000') || ($positive && $number->isZero())) self::fail($field);
        return $number;
    }

    public static function money(mixed $value, string $field): int
    {
        $number = self::decimal($value, $field);
        try { return $number->toScale(0, RoundingMode::Unnecessary)->toInt(); }
        catch (\Throwable) { self::fail($field); }
    }

    public static function count(mixed $value, string $field): int
    {
        $count = self::money($value, $field);
        if ($count < 1 || $count > 1000) self::fail($field);
        return $count;
    }

    public static function rounded(BigDecimal $number): int
    {
        if ($number->isNegative() || $number->isGreaterThan('1000000000000')) self::fail('montant');
        return $number->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    public static function text(BigDecimal $number): string { return (string) $number->strippedOfTrailingZeros(); }
    public static function fail(string $field): never { throw new InvalidArgumentException('Valeur invalide : '.$field.'.'); }
}
