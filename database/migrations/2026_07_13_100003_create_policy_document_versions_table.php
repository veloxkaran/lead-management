<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->longText('content');
            $table->date('effective_date');
            $table->timestamp('published_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['policy_document_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_document_versions');
    }
};
