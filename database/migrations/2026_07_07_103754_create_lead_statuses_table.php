<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#6c757d');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_closed_won')->default(false);
            $table->boolean('is_closed_lost')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
