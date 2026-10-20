<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentExclude extends Model
{
    protected $table = 'incident_excludes';

    protected $primaryKey = 'id';

    protected $fillable = [
        'incident',
        'keterangan_exclude',
        'created_by',
    ];
}
