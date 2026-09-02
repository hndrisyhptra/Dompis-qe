<?php

namespace App\Models;

<<<<<<< HEAD
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

=======
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
    protected $primaryKey = 'id_branch';

    protected $fillable = [
        'code',
        'name',
        'region',
<<<<<<< HEAD
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

=======
    ];

>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }
<<<<<<< HEAD

    /**
     * Relasi ke master Region. Dinamai regionRef() (bukan region()) supaya
     * tidak bentrok dengan kolom string denormalisasi `branches.region`
     * yang masih dibaca banyak tempat (dashboard, evidence-approval).
     */
    public function regionRef(): BelongsTo
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
=======
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
}
