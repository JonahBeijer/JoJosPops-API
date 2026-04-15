<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pop extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'neighbourhood',
        'location',
        'genre',
        'date',
        'time',
        'access',
        'event_type',
        'reveal_time'
    ];

    protected $casts = [
        'reveal_time' => 'datetime',
        'date' => 'date',
    ];

}
