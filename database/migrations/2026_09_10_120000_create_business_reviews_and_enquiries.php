<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_reviews')) {
            Schema::create('business_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')
                    ->constrained('businesses')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->unsignedTinyInteger('rating');
                $table->string('title', 120)->nullable();
                $table->text('body');

                // pending | approved | rejected
                $table->string('status', 30)->default('pending');

                $table->foreignId('moderated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->text('moderation_notes')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->timestamp('published_at')->nullable();

                $table->timestamps();

                $table->unique(
                    ['business_id', 'user_id'],
                    'business_reviews_business_user_unique'
                );

                $table->index(
                    ['business_id', 'status', 'published_at'],
                    'business_reviews_public_index'
                );

                $table->index(
                    ['user_id', 'status'],
                    'business_reviews_user_status_index'
                );
            });
        }

        if (! Schema::hasTable('business_enquiries')) {
            Schema::create('business_enquiries', function (Blueprint $table) {
                $table->id();

                $table->foreignId('business_id')
                    ->constrained('businesses')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // general | quote
                $table->string('request_type', 30)->default('general');

                $table->string('contact_name', 120);
                $table->string('contact_email', 180)->nullable();
                $table->string('contact_phone', 40)->nullable();

                // whatsapp | phone | email
                $table->string('preferred_channel', 20)->default('whatsapp');

                $table->text('message');

                // new | viewed | responded | closed | spam
                $table->string('status', 30)->default('new');

                // recorded | owner_notified | platform_notified | notification_failed
                $table->string('delivery_status', 40)->default('recorded');

                $table->text('owner_notes')->nullable();
                $table->text('admin_notes')->nullable();

                $table->string('source_url', 1000)->nullable();
                $table->char('ip_hash', 64)->nullable();

                $table->timestamp('consent_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('seen_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->timestamps();

                $table->index(
                    ['business_id', 'status', 'created_at'],
                    'business_enquiries_business_status_index'
                );

                $table->index(
                    ['user_id', 'created_at'],
                    'business_enquiries_user_index'
                );

                $table->index(
                    ['request_type', 'status'],
                    'business_enquiries_type_status_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_enquiries');
        Schema::dropIfExists('business_reviews');
    }
};
