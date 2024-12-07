<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserHierarchy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ancestorId',
        'descendantId',
        'depth',
    ];

    public function ancestor()
    {
        return $this->belongsTo(User::class, 'ancestorId');
    }

    public function descendant()
    {
        return $this->belongsTo(User::class, 'descendantId');
    }
}
