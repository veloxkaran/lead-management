<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_data_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_data_id')->constrained('raw_data')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['raw_data_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_data_assignment_logs');
    }
};
