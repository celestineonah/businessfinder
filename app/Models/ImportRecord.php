<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRecord extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'entity_type',
        'entity_id',
        'external_key',
        'action',
        'fingerprint',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'entity_id' => 'integer',
            'payload' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
