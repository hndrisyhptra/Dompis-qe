<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QeBoqHistory extends Model
{
    protected $table = 'qe_boq_histories';

    protected $primaryKey = 'id_boq_history';

    protected $fillable = [
        'qe_boq_id', 'user_id', 'event_type', 'before_data', 'after_data', 'note',
    ];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array'];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(QeBoq::class, 'qe_boq_id', 'id_boq');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }
}
