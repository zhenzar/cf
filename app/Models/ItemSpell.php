<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSpell extends Model
{
    protected $fillable = ['item_id', 'name', 'level'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
