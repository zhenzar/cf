<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemProtection extends Model
{
    protected $fillable = ['item_id', 'type', 'value'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
