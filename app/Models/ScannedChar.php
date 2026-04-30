<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannedChar extends Model
{
    protected $fillable = ['name', 'race', 'class', 'level', 'log_file_id', 'source_line'];

    public function logFile(): BelongsTo
    {
        return $this->belongsTo(LogFile::class);
    }
}
