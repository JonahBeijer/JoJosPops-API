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
        'images',
        'reveal_time',
        // 👇 NIEUW: Voeg deze twee toe!
        'is_ticketed',
        'ticket_price',
    ];

    protected $casts = [
        'reveal_time' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'date' => 'date:Y-m-d',
        'images' => 'array',
        // 👇 NIEUW: Zorg dat de datatypes altijd kloppen
        'is_ticketed' => 'boolean',
        'ticket_price' => 'decimal:2',
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
