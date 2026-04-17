<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Race extends Model
{
    protected $fillable = ['name', 'cost', 'allowed_alignments', 'description'];

    protected $casts = [
        'allowed_alignments' => 'array',
        'cost' => 'integer',
    ];
}
