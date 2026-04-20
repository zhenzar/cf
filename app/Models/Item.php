<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'log_file_id', 'name', 'keyword', 'worth_copper', 'level',
        'item_type', 'slot', 'material', 'weight_pounds', 'weight_ounces',
        'weapon_class', 'damage_type', 'av_damage', 'raw_text', 'hash',
    ];

    public function logFile(): BelongsTo
    {
        return $this->belongsTo(LogFile::class);
    }

    public function affects(): HasMany
    {
        return $this->hasMany(ItemAffect::class);
    }

    public function protections(): HasMany
    {
        return $this->hasMany(ItemProtection::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ItemFlag::class);
    }
}
