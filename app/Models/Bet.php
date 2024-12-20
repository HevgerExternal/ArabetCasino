<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trade_id',
        'bet_amount',
        'win_amount',
        'type',
        'provider',
        'currency',
        'settle_status',
        'info'
    ];

    protected $casts = [
        'type' => 'string',
    ];

    protected $hidden = [
        'provider',
        'info'
    ];
}
