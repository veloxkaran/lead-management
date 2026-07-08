<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('target', 14, 2);
            $table->decimal('achieved', 14, 2)->default(0);
            $table->string('goal_type');
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('bs_year')->nullable();
            $table->unsignedTinyInteger('bs_month')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['goal_type', 'bs_year', 'bs_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
