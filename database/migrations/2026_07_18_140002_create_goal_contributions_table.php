<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('contribution_type');
            $table->decimal('amount', 14, 2);
            $table->date('contributed_at');
            $table->timestamps();

            $table->unique(['goal_id', 'source_type', 'source_id']);
            $table->index(['goal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
