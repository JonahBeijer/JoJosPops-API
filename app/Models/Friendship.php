<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $table = 'friendships';
    protected $fillable = ['user_id', 'friend_id', 'status'];

    /**
     * De relatie naar de gebruiker die het verzoek heeft VERZONDEN
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
