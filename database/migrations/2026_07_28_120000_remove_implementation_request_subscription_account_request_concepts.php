<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes Implementation Requests, Subscriptions, and Account Requests
 * entirely, per explicit user request. Training and Support Tickets are
 * untouched — separate features that happened to sit alongside these in
 * the same "Customer Success progress" UI sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('implementation_requests');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('account_requests');
    }

    public function down(): void
    {
        Schema::create('implementation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('status')->default('not_started');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->date('planned_date')->nullable();
            $table->unsignedInteger('completion_percentage')->default(0);
            $table->string('phase')->nullable();
            $table->text('notes')->nullable();

            $table->index(['company_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('plan_name');
            $table->string('status')->default('trial');
            $table->date('contract_start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('licensed_users')->nullable();
            $table->integer('active_users')->nullable();
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('renewal_amount')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->date('last_payment_date')->nullable();
            $table->decimal('outstanding_amount')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('request_type')->default('invoice');
            $table->decimal('amount')->nullable();
            $table->text('details')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }
};
