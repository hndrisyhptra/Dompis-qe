<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeMaterialReservation extends Model
{
    use SoftDeletes;

    protected $table = 'qe_material_reservations';

    protected $primaryKey = 'id_reservation';

    protected $fillable = ['qe_lop_id', 'technician_id', 'status', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function lop(): BelongsTo
    {
        return $this->belongsTo(QeLop::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id', 'id_user');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QeMaterialReservationItem::class, 'reservation_id', 'id_reservation');
    }
}
