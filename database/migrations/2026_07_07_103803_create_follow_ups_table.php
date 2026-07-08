<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->date('follow_up_date');
            $table->time('follow_up_time');
            $table->unsignedInteger('reminder_minutes_before')->default(30);
            $table->string('reminder_type')->default('email');
            $table->string('status')->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['follow_up_date', 'follow_up_time', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
