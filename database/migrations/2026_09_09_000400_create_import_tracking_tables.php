<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // geography | taxonomy | businesses | other
            $table->string('import_type', 50);

            $table->string('source_name', 180);
            $table->string('source_url', 1000)->nullable();
            $table->string('original_filename', 255)->nullable();

            // SHA-256 of the source file/content
            $table->string('source_hash', 64)->nullable()->index();

            // pending | running | completed | partial | failed
            $table->string('status', 30)->default('pending');

            $table->unsignedBigInteger('rows_total')->default(0);
            $table->unsignedBigInteger('rows_succeeded')->default(0);
            $table->unsignedBigInteger('rows_failed')->default(0);
            $table->unsignedBigInteger('rows_skipped')->default(0);

            $table->json('metadata')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['import_type', 'status']);
            $table->index(['source_name', 'created_at']);
        });

        Schema::create('import_failures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_batch_id')
                ->constrained('import_batches')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('row_number')->nullable();
            $table->string('external_key', 191)->nullable();

            // error | warning
            $table->string('severity', 20)->default('error');

            $table->string('code', 100)->nullable();
            $table->text('message');

            // Original row / relevant diagnostic data
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index([
                'import_batch_id',
                'severity',
            ]);

            $table->index([
                'import_batch_id',
                'row_number',
            ]);

            $table->index('external_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_failures');
        Schema::dropIfExists('import_batches');
    }
};
