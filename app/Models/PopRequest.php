<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopRequest extends Model
{
    protected $fillable = ['user_id', 'pop_id', 'status'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function pop() {
        return $this->belongsTo(Pop::class);
    }


}
