<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('closed_by')->constrained('users')->cascadeOnDelete();
            $table->date('closed_date');
            $table->decimal('deal_value', 14, 2);
            $table->text('closing_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_closures');
    }
};
