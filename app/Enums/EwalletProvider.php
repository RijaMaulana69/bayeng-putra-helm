<?php

namespace App\Enums;

enum EwalletProvider: string
{
    case DANA = 'DANA';
    case GOPAY = 'GoPay';
    case SHOPEEPAY = 'ShopeePay';

    /**
     * Dapatkan seluruh nilai string dari enum EwalletProvider.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
