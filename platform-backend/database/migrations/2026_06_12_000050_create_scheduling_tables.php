<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling core: recurring schedules (stored as LOCAL time + the location
 * timezone, never UTC — see docs/30 §2), punctual exceptions, customers,
 * consents and the appointment aggregate with denormalized snapshots.
 *
 * Anti double-booking: appointment_items carries a UNIQUE
 * (tenant_id, staff_member_id, starts_at, is_blocking) index where
 * is_blocking is 1 for active items and NULL for cancelled ones (NULL rows
 * escape the constraint in both MySQL and SQLite). Identical start times are
 * therefore rejected at the storage layer; partial overlaps are prevented by
 * the locking transaction in BookAppointment (docs/30 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Monday … 6 = Sunday (ISO-8601)
            $table->time('start_time');             // local time of the location
            $table->time('end_time');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'location_id', 'weekday']);
        });

        Schema::create('staff_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'staff_member_id', 'weekday']);
        });

        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('scope', 10); // location | staff
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->time('time_start')->nullable(); // whole day when NULL
            $table->time('time_end')->nullable();
            $table->string('kind', 15); // closed | open_extra
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'date_start', 'date_end']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('birthdate')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->string('source', 10); // app | staff | import
            $table->unsignedSmallInteger('no_show_count')->default(0);
            $table->timestamp('last_appointment_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
        });

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->string('visibility', 10); // internal | clinical
            $table->text('body'); // encrypted at rest via model cast
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        // Append-only GDPR consent history: every change is a new row.
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->boolean('granted');
            $table->string('source', 20);
            $table->timestamp('occurred_at');
            $table->index(['tenant_id', 'customer_id', 'kind']);
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status', 25);
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');   // UTC
            $table->unsignedInteger('total_price_cents');
            $table->char('currency', 3);
            $table->string('source', 10); // app | staff | import
            $table->string('idempotency_key', 64)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'location_id', 'starts_at']);
            $table->index(['tenant_id', 'customer_id', 'starts_at']);
            $table->index(['tenant_id', 'status', 'starts_at']);
        });

        Schema::create('appointment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            // Snapshots: history survives later catalog changes (docs/24 §1).
            $table->string('service_name_snapshot');
            $table->string('variant_name_snapshot');
            $table->unsignedSmallInteger('duration_minutes_snapshot');
            $table->unsignedSmallInteger('buffer_minutes_snapshot');
            $table->unsignedInteger('price_cents_snapshot');
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');   // UTC, buffer included
            $table->unsignedTinyInteger('position')->default(0);
            // 1 when the item blocks the agenda, NULL otherwise (cancelled).
            $table->unsignedTinyInteger('is_blocking')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'staff_member_id', 'starts_at', 'is_blocking'],
                'appt_items_no_identical_start'
            );
            $table->index(['tenant_id', 'staff_member_id', 'starts_at'], 'appt_items_staff_range');
        });

        Schema::create('appointment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'appointment_id']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('preferred_staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 15);
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('appointment_events');
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('location_schedules');
    }
};
