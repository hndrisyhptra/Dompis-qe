<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QeLopHistory extends Model
{
    protected $table = 'qe_lop_histories';

    protected $primaryKey = 'id_qe_lop_histories';

    protected $fillable = [
        'qe_lop_id',
        'user_id',
        'status_before',
        'status_after',
        'event_type',
        'note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function lop(): BelongsTo
    {
        return $this->belongsTo(QeLop::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
