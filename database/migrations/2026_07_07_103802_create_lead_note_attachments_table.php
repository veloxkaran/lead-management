<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_note_id')->constrained('lead_notes')->cascadeOnDelete();
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_note_attachments');
    }
};
