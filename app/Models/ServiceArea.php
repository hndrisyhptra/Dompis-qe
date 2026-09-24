<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceArea extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_areas';

    protected $primaryKey = 'id_service_area';

    protected $fillable = [
        'workzone',
        'name',
        'branch_id',
        'region_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id_branch');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id_region');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lops(): HasMany
    {
        return $this->hasMany(QeLop::class, 'service_area_id', 'id_service_area');
    }

    public function scopedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_service_areas', 'service_area_id', 'user_id');
    }
}
