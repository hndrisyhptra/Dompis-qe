<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $primaryKey = 'id_branch';

    protected $fillable = [
        'code',
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }
}
