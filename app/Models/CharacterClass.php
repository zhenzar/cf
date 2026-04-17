<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterClass extends Model
{
    protected $fillable = ['name', 'allowed_alignments', 'description', 'exclusive_race_name'];

    protected $casts = [
        'allowed_alignments' => 'array',
    ];
}
