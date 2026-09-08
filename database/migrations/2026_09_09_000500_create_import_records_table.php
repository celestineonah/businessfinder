<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_batch_id')
                ->constrained('import_batches')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('row_number')->nullable();

            // lga | city | area | category | business | ...
            $table->string('entity_type', 80);

            // Generic entity ID; intentionally not a foreign key.
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('external_key', 191)->nullable();

            // inserted | updated | skipped
            $table->string('action', 30);

            // SHA-256 fingerprint of normalized source row.
            $table->string('fingerprint', 64)->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(
                ['import_batch_id', 'entity_type', 'action'],
                'import_record_batch_action_index'
            );

            $table->index(
                ['entity_type', 'entity_id'],
                'import_record_entity_index'
            );

            $table->index(
                ['import_batch_id', 'row_number'],
                'import_record_row_index'
            );

            $table->index('external_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_records');
    }
};
