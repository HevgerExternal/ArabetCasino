<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalProvider extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Define the relationship with Provider.
     */
    public function providers()
    {
        return $this->hasMany(Provider::class, 'external_provider_id');
    }
}
