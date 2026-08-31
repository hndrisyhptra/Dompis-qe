<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QeSurvey extends Model
{
    protected $table = 'qe_surveys';

    protected $primaryKey = 'id_survey';

    protected $fillable = [
        'qe_lop_id', 'captured_by', 'latitude', 'longitude', 'accuracy',
        'location_source', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    public function lop(): BelongsTo
    {
        return $this->belongsTo(QeLop::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by', 'id_user');
    }
}
