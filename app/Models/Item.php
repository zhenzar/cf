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
        'weapon_class', 'weapon_qualifier', 'damage_type', 'attack_type',
        'damage_dice', 'av_damage', 'raw_text', 'hash', 'stats_hash', 'status',
    ];

    /**
     * Compute a canonical hash of all identifying stats for dedup.
     * Relations (protections, affects, flags) must be pre-loaded or loadable.
     */
    public function computeStatsHash(): string
    {
        $this->loadMissing(['protections', 'affects', 'flags']);

        $norm = function ($v) {
            if (is_string($v)) return trim(strtolower($v));
            return $v;
        };

        $protections = $this->protections
            ->map(fn ($p) => strtolower($p->type) . ':' . $p->value)
            ->sort()->values()->all();
        $affects = $this->affects
            ->map(fn ($a) => strtolower($a->stat) . ':' . $a->modifier)
            ->sort()->values()->all();
        $flags = $this->flags
            ->map(fn ($f) => strtolower($f->flag))
            ->sort()->values()->all();

        $payload = [
            'name'             => $norm($this->name),
            'keyword'          => $norm($this->keyword),
            'worth_copper'     => $this->worth_copper,
            'level'            => $this->level,
            'item_type'        => $norm($this->item_type),
            'slot'             => $norm($this->slot),
            'material'         => $norm($this->material),
            'weight_pounds'    => $this->weight_pounds,
            'weight_ounces'    => $this->weight_ounces,
            'weapon_class'     => $norm($this->weapon_class),
            'weapon_qualifier' => $norm($this->weapon_qualifier),
            'damage_type'      => $norm($this->damage_type),
            'attack_type'      => $norm($this->attack_type),
            'damage_dice'      => $norm($this->damage_dice),
            'av_damage'        => $this->av_damage,
            'protections'      => $protections,
            'affects'          => $affects,
            'flags'            => $flags,
        ];

        return hash('sha256', json_encode($payload));
    }

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
