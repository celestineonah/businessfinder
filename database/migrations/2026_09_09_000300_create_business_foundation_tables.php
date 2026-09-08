<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name', 180);
            $table->string('normalized_name', 180)->index();
            $table->string('slug', 200)->unique();

            $table->string('legal_name', 200)->nullable();
            $table->string('cac_number', 80)->nullable()->index();

            $table->string('primary_phone', 40)->nullable();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->string('primary_email', 180)->nullable();
            $table->string('website_url', 500)->nullable();

            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();

            // listed | suspended | archived
            $table->string('listing_status', 30)->default('listed');

            // unclaimed | pending | claimed
            $table->string('claim_status', 30)->default('unclaimed');

            // unverified | pending | verified | rejected
            $table->string('verification_status', 30)
                ->default('unverified');

            $table->string('dedupe_key', 191)->nullable()->index();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'listing_status',
                'verification_status',
                'is_active',
            ]);

            $table->index([
                'claim_status',
                'is_active',
            ]);
        });

        Schema::create('business_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('state_id')
                ->nullable()
                ->constrained('states')
                ->restrictOnDelete();

            $table->foreignId('city_id')
                ->nullable()
                ->constrained('cities')
                ->restrictOnDelete();

            $table->foreignId('local_government_area_id')
                ->nullable()
                ->constrained('local_government_areas')
                ->nullOnDelete();

            $table->foreignId('area_id')
                ->nullable()
                ->constrained('areas')
                ->nullOnDelete();

            $table->string('name', 150)->nullable();
            $table->string('slug', 180);

            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->string('postal_code', 30)->nullable();

            $table->string('phone', 40)->nullable();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->string('email', 180)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->json('opening_hours')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('service_area_only')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['business_id', 'slug'],
                'business_location_slug_unique'
            );

            $table->index([
                'state_id',
                'city_id',
                'area_id',
                'is_active',
            ], 'business_location_geo_index');

            $table->index([
                'latitude',
                'longitude',
            ], 'business_location_coordinates_index');

            $table->index([
                'business_id',
                'is_primary',
                'is_active',
            ], 'business_location_primary_index');
        });

        Schema::create('business_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(
                ['business_id', 'category_id'],
                'business_category_unique'
            );

            $table->index([
                'category_id',
                'is_primary',
            ]);
        });

        Schema::create('business_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('business_location_id')
                ->nullable()
                ->constrained('business_locations')
                ->cascadeOnDelete();

            // owner | website | directory | cac | social | manual | import
            $table->string('source_type', 50);

            $table->string('source_name', 150)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('external_id', 191)->nullable();

            // 0.0000 to 1.0000
            $table->decimal('confidence_score', 5, 4)
                ->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'business_id',
                'source_type',
                'is_active',
            ], 'business_source_lookup_index');

            $table->index([
                'source_name',
                'external_id',
            ], 'business_source_external_index');

            $table->index('last_checked_at');
        });

        Schema::create('business_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // phone | email | website | cac | document | manual
            $table->string('claim_method', 40)->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 30)->default('pending');

            $table->text('claimant_notes')->nullable();
            $table->text('review_notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'status',
            ]);

            $table->index([
                'user_id',
                'status',
            ]);
        });

        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('business_location_id')
                ->nullable()
                ->constrained('business_locations')
                ->cascadeOnDelete();

            $table->foreignId('verified_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // phone | email | cac | address | website | representative
            $table->string('verification_type', 40);

            // pending | verified | failed | expired
            $table->string('status', 30)->default('pending');

            $table->string('reference', 255)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'verification_type',
                'status',
            ], 'business_verification_lookup_index');

            $table->index([
                'status',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_verifications');
        Schema::dropIfExists('business_claims');
        Schema::dropIfExists('business_sources');
        Schema::dropIfExists('business_category');
        Schema::dropIfExists('business_locations');
        Schema::dropIfExists('businesses');
    }
};
