<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geopolitical_zones', function (Blueprint $table) {
            $table->id();

            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('code', 2)->unique();

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('geopolitical_zone_id')
                ->constrained('geopolitical_zones')
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('code', 2)->unique();

            $table->boolean('is_fct')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['geopolitical_zone_id', 'is_active']);
        });

        Schema::create('local_government_areas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('state_id')
                ->constrained('states')
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('slug', 120);

            // lga | area_council
            $table->string('administrative_type', 30)->default('lga');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['state_id', 'slug']);
            $table->index(['state_id', 'is_active']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('state_id')
                ->constrained('states')
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('slug', 120);

            $table->boolean('is_state_capital')->default(false);
            $table->boolean('is_major')->default(false);
            $table->boolean('is_active')->default(true);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(['state_id', 'slug']);
            $table->index(['state_id', 'is_major', 'is_active']);
        });

        Schema::create('city_local_government_area', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->cascadeOnDelete();

            $table->foreignId('local_government_area_id')
                ->constrained('local_government_areas')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['city_id', 'local_government_area_id'],
                'city_lga_unique'
            );
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('state_id')
                ->constrained('states')
                ->restrictOnDelete();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->foreignId('local_government_area_id')
                ->nullable()
                ->constrained('local_government_areas')
                ->nullOnDelete();

            $table->string('name', 150);
            $table->string('slug', 150);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['city_id', 'slug']);
            $table->index(['state_id', 'city_id', 'is_active']);
            $table->index(['local_government_area_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('city_local_government_area');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('local_government_areas');
        Schema::dropIfExists('states');
        Schema::dropIfExists('geopolitical_zones');
    }
};
