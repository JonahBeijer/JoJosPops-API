<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pop extends Model
{
    protected $fillable = [
        'user_id',
        'image_emoji',
        'title',
        'neighbourhood',
        'location',
        'latitude',
        'longitude',
        'genre',
        'description',
        'capacity',
        'current_guests',
        'date',
        'event_time',
        'access',
        'event_type',
        'images', // Holds array structures perfectly via the cast mapping below
        'reveal_time',
    ];

    protected $casts = [
        'reveal_time' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'date' => 'date:Y-m-d',
        'images' => 'array', // 👈 ADD THIS: Converts JSON database rows natively into clean PHP Arrays
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function requests()
    {
        return $this->hasMany(PopRequest::class, 'pop_id');
    }
}
