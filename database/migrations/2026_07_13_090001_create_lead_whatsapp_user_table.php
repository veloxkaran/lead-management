<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_whatsapp_user', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['lead_id', 'user_id'], 'lead_whatsapp_user_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_whatsapp_user');
    }
};
