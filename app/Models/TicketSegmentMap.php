<?php

namespace App\Models;

use App\Enums\LopSegment;
use Illuminate\Database\Eloquent\Model;

class TicketSegmentMap extends Model
{
    protected $primaryKey = 'id_ticket_segment_map';

    protected $fillable = [
        'source_value',
        'segment',
    ];

    protected function casts(): array
    {
        return [
            'segment' => LopSegment::class,
        ];
    }
}
