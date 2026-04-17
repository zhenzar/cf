<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['name', 'realm', 'min_level', 'max_level'];

    protected $casts = [
        'min_level' => 'integer',
        'max_level' => 'integer',
    ];

    public function characters()
    {
        return $this->belongsToMany(Character::class)
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function isAllLevels(): bool
    {
        return $this->min_level === 1 && $this->max_level === 51;
    }

    public function levelRangeLabel(): string
    {
        return $this->isAllLevels() ? 'All' : "{$this->min_level}–{$this->max_level}";
    }
}
