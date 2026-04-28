<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mob extends Model
{
    protected $fillable = ['name', 'area_id', 'notes'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(MobEquipment::class);
    }
}
