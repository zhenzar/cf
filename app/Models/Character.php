<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    protected $fillable = ['user_id', 'name', 'race_id', 'character_class_id', 'sphere_id', 'alignment'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function race()
    {
        return $this->belongsTo(Race::class);
    }

    public function characterClass()
    {
        return $this->belongsTo(CharacterClass::class);
    }

    public function sphere()
    {
        return $this->belongsTo(Sphere::class);
    }
}
