<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'image',
        'external_provider_id',
        'type',
    ];

    /**
     * Define the relationship with ExternalProvider.
     */
    public function externalProvider()
    {
        return $this->belongsTo(ExternalProvider::class, 'external_provider_id');
    }
}
