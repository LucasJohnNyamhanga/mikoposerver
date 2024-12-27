<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ofisi extends Model
{
     public function users()
    {
        return $this->belongsToMany(User::class, 'user_ofisis')->latest()
            ->withPivot('position_id', 'status')
            ->withTimestamps()
            ->leftJoin('positions', 'user_ofisis.position_id', '=', 'positions.id')
            ->select('users.*', 'positions.name as position_name');
            // ->wherePivot('status', 'accepted'); // Filter where pivot status is denied
    }

    public function positions()
    {
        return $this->hasManyThrough(Position::class, UserOfisi::class, 'ofisi_id', 'id', 'id', 'position_id');
    }

    public function positionsWithUsers()
    {
        return $this->positions()->with(['users' => function ($query) {
            $query->wherePivot('ofisi_id', $this->id);
        }]);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

}
