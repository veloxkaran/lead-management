<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('activity_type');
            $table->date('activity_date');
            $table->time('activity_time');
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
