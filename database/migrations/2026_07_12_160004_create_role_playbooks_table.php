<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
            $table->json('responsibilities');
            $table->json('sops');
            $table->json('success_metrics');
            $table->string('motivation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_playbooks');
    }
};
