<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('plan_name');
            $table->string('status')->default('trial');
            $table->date('contract_start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('licensed_users')->nullable();
            $table->unsignedInteger('active_users')->nullable();
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('renewal_amount', 12, 2)->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->date('last_payment_date')->nullable();
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
