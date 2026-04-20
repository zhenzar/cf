<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogFile extends Model
{
    protected $fillable = [
        'path', 'filename', 'source', 'size', 'reviewed', 'items_count', 'scanned_at',
    ];

    protected $casts = [
        'reviewed' => 'boolean',
        'scanned_at' => 'datetime',
        'size' => 'integer',
        'items_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
