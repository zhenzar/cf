<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemLogFile extends Pivot
{
    protected $table = 'item_log_file';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
