<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QeMaterialReservationItem extends Model
{
    protected $table = 'qe_material_reservation_items';

    protected $primaryKey = 'id_reservation_item';

    protected $fillable = ['reservation_id', 'designator_id', 'qty', 'qty_actual'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'qty_actual' => 'decimal:3'];
    }

    /**
     * Material sisa (belum terpakai). Null selama qty aktual belum direkap.
     */
    public function sisa(): ?float
    {
        if ($this->qty_actual === null) {
            return null;
        }

        return max(0, (float) $this->qty - (float) $this->qty_actual);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(QeMaterialReservation::class, 'reservation_id', 'id_reservation');
    }

    public function designator(): BelongsTo
    {
        return $this->belongsTo(Designator::class, 'designator_id', 'id_designator')->withTrashed();
    }
}
