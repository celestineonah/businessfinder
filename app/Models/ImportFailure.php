<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFailure extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'external_key',
        'severity',
        'code',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'payload' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
