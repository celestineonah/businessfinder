<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'import_type',
        'source_name',
        'source_url',
        'original_filename',
        'source_hash',
        'status',
        'rows_total',
        'rows_succeeded',
        'rows_failed',
        'rows_skipped',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_succeeded' => 'integer',
            'rows_failed' => 'integer',
            'rows_skipped' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class);
    }
}
