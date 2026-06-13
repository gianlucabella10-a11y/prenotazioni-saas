<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog & resources: locations, service catalog (every service has at
 * least one variant carrying duration/price) and bookable staff members.
 *
 * Soft deletes everywhere historical records (appointments) keep references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            // IANA timezone: recurring schedules are interpreted in this zone.
            $table->string('timezone', 64);
            $table->string('status', 20);
            $table->unsignedSmallInteger('booking_window_days');
            $table->unsignedInteger('cancellation_cutoff_minutes');
            $table->unsignedInteger('min_notice_minutes');
            $table->unsignedSmallInteger('slot_granularity_minutes');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('service_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'service_id']);
        });

        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('role_label')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_bookable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'is_bookable']);
        });

        Schema::create('staff_services', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['staff_member_id', 'service_id']);
            $table->index(['tenant_id', 'service_id']);
        });

        Schema::create('location_services', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['location_id', 'service_id']);
            $table->index(['tenant_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_services');
        Schema::dropIfExists('staff_services');
        Schema::dropIfExists('staff_members');
        Schema::dropIfExists('service_variants');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('locations');
    }
};
