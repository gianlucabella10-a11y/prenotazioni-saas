<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging & compliance: the notification outbox (DB is the source of
 * truth, queue jobs are derived — docs/29 §3,7), broadcast campaigns and the
 * append-only audit log.
 *
 * notification_records and audit_logs deliberately have no FK constraints on
 * high-volume references: they are partitioning candidates and MySQL does
 * not support FKs on partitioned tables (docs/33 #15). Integrity is enforced
 * at the application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 10);  // push | email | sms
            $table->string('template_code', 50);
            $table->json('payload');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('status', 25);
            $table->dateTime('scheduled_for'); // UTC
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['status', 'scheduled_for']);
            $table->index(['tenant_id', 'appointment_id']);
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('message');
            $table->json('segment'); // selection criteria, validated by JSON schema
            $table->string('channel', 10);
            $table->string('status', 15);
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('notification_records');
    }
};
