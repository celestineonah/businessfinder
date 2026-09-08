<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('slug', 120)->unique();

            $table->text('description')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sector_id')
                ->constrained('sectors')
                ->restrictOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 150)->unique();

            $table->string('singular_name', 150)->nullable();
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->index(['sector_id', 'is_active']);
            $table->index(['parent_id', 'is_active']);
            $table->index(['depth', 'is_active']);
        });

        Schema::create('category_aliases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->string('name', 180);
            $table->string('normalized_name', 180);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['category_id', 'normalized_name'],
                'category_alias_unique'
            );

            $table->index(['normalized_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_aliases');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sectors');
    }
};
