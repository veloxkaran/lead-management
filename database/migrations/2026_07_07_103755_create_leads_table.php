<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->unsignedInteger('number_of_employees')->nullable();
            $table->text('business_details')->nullable();
            $table->text('about_client_business')->nullable();
            $table->string('source')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_user_id', 'lead_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
