<?php

namespace App\Service;

class IsbnService
{
    /**
     * 値がISBN-10またはISBN-13として妥当かチェックします。
     */
    public static function isValidIsbn(string $isbn): bool
    {
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $isbn);
        $length = strlen($cleanIsbn);

        if ($length === 10) {
            return self::isValidIsbn10($cleanIsbn);
        }

        if ($length === 13) {
            return self::isValidIsbn13($cleanIsbn);
        }

        return false;
    }

    /**
     * ISBN-10のチェックデジットを検証します。
     */
    public static function isValidIsbn10(string $isbn10): bool
    {
        $isbn10 = preg_replace('/[^0-9X]/i', '', $isbn10);
        if (strlen($isbn10) !== 10) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$isbn10[$i] * (10 - $i);
        }

        $checkDigit = strtoupper($isbn10[9]);
        $sum += ($checkDigit === 'X') ? 10 : (int)$checkDigit;

        return ($sum % 11 === 0);
    }

    /**
     * ISBN-13のチェックデジットを検証します。
     */
    public static function isValidIsbn13(string $isbn13): bool
    {
        $isbn13 = preg_replace('/[^0-9]/', '', $isbn13);
        if (strlen($isbn13) !== 13) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$isbn13[$i] * (($i % 2 === 0) ? 1 : 3);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return ((int)$isbn13[12] === $checkDigit);
    }

    /**
     * ISBN-10をISBN-13に変換します。
     */
    public static function convertToIsbn13(string $isbn10): ?string
    {
        if (!self::isValidIsbn10($isbn10)) {
            if (!self::isValidIsbn13($isbn10)) {
                //ISBNでない場合はnullを返す
                return null;
            }
            // 13桁の場合はきれいにしてそのまま返す
            return preg_replace('/[^0-9X]/i', '', $isbn10);
        }

        $cleanIsbn10 = preg_replace('/[^0-9X]/i', '', $isbn10);
        $isbn13Base = '978' . substr($cleanIsbn10, 0, 9);
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$isbn13Base[$i] * (($i % 2 === 0) ? 1 : 3);
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return $isbn13Base . $checkDigit;
    }
}
