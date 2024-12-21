<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['currency', 'rate', 'date'];

    /**
     * Get the latest exchange rate for the specified currency.
     *
     * @param string $currency
     * @return float|null
     */
    public static function getRate($currency)
    {
        // Retrieve the most recent rate from the database
        $rate = self::where('currency', $currency)
            ->orderBy('date', 'desc')
            ->first();

        return $rate ? $rate->rate : null;
    }
}
