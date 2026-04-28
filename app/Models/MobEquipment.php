<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobEquipment extends Model
{
    protected $fillable = ['mob_id', 'slot', 'item_name', 'item_id'];

    public function mob(): BelongsTo
    {
        return $this->belongsTo(Mob::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
