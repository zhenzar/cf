<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAffect extends Model
{
    protected $fillable = ['item_id', 'stat', 'modifier'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
