<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportsTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'amount',
        'win_amount',
        'currency',
        'type',
        'game_type',
        'settle_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
