<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email_verified_at')->index();
        });

        Schema::create('geography_lga_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('local_government_area_id')->constrained('local_government_areas')->cascadeOnDelete();
            $table->string('source_name', 100)->default('OpenStreetMap');
            $table->string('external_name', 180);
            $table->string('normalized_external_name', 180);
            $table->string('status', 30)->default('pending');
            $table->string('evidence_url', 1000)->nullable();
            $table->decimal('confidence_score', 5, 4)->default(1.0000);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_name', 'state_id', 'normalized_external_name'], 'geo_lga_alias_source_state_name_unique');
            $table->index(['state_id', 'local_government_area_id', 'status'], 'geo_lga_alias_lookup_index');
        });

        Schema::table('business_claims', function (Blueprint $table) {
            $table->index(['business_id', 'status', 'submitted_at'], 'business_claim_review_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('business_claims', function (Blueprint $table) {
            $table->dropIndex('business_claim_review_queue_index');
        });
        Schema::dropIfExists('geography_lga_aliases');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};
