<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_data_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_data_id')->constrained('raw_data')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();

            $table->index('raw_data_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_data_comments');
    }
};
