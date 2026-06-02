<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // 🔑 1. Importeer de Sanctum trait

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // 🔑 2. Voeg HasApiTokens hier toe

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'profile_image'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * De Pops die door deze gebruiker zijn aangemaakt (1-op-veel relatie).
     */
    public function pops()
    {
        return $this->hasMany(\App\Models\Pop::class);
    }

    /**
     * De Pops die deze gebruiker als favoriet heeft gemarkeerd (veel-op-veel relatie).
     */
    public function favoritePops()
    {
        return $this->belongsToMany(\App\Models\Pop::class, 'pop_user_favorites')
            ->withTimestamps();
    }

    public function friends()
    {
        // Haalt alle gebruikers op die gekoppeld zijn via de friendships tabel als 'friend_id'
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function popRequests()
    {
        return $this->hasMany(\App\Models\PopRequest::class, 'user_id');
    }
}
