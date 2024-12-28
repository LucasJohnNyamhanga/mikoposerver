<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function maofisi()
    {
        return $this->belongsToMany(Ofisi::class, 'user_ofisis')
                    ->withPivot('position_id', 'status')
                    ->withTimestamps();
    }

    public function positions()
    {
        return $this->hasManyThrough(Position::class, UserOfisi::class, 'user_id', 'id', 'id', 'position_id');
    }

    public function activeOfisi()
    {
        return $this->hasOne(Active::class);
        // return instance of ative kikundi with kikundi name and other details
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }



    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mobile',
        'jina_kamili',
        'jina_mdhamini',
        'simu_mdhamini',
        'picha',
        'is_manager',
        'is_admin',
        'username',
        'is_active',
        'password',
        'anakoishi',
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
            // 'email_verified_at' => 'datetime',
            // 'password' => 'hashed',
        ];
    }
}
