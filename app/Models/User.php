<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'balance',
        'parentId',
        'roleId',
        'status',
        'last_accessed',
    ];

    protected $hidden = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
    ];

    protected $casts = [
        'balance' => 'float',
        'status' => 'boolean',
        'last_accessed' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parentId');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parentId');
    }

    /**
     * Get the settings associated with the user.
     */
    public function settings()
    {
        return $this->hasOne(UserSettings::class, 'user_id');
    }
}
