<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'target_type',
        'target_id',
        'reason',
        'description',
        'status'
    ];

    // De persoon die rapporteert
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
