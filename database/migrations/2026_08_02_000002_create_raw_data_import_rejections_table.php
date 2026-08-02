<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_data_import_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_data_import_batch_id')->constrained('raw_data_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('errors');
            $table->json('raw_data');
            $table->timestamps();

            $table->index('raw_data_import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_data_import_rejections');
    }
};
