<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogFile extends Model
{
    protected $fillable = [
        'path', 'filename', 'source', 'size', 'content_hash', 'content',
        'reviewed', 'items_count', 'scanned_at',
    ];

    protected $casts = [
        'reviewed' => 'boolean',
        'scanned_at' => 'datetime',
        'size' => 'integer',
        'items_count' => 'integer',
    ];

    /**
     * All items seen in this log file (many-to-many; same item can appear in many logs).
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)
            ->withPivot('created_at')
            ->using(\App\Models\ItemLogFile::class);
    }

    /**
     * Items where this LogFile was the original/first source (primary).
     */
    public function originalItems(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
