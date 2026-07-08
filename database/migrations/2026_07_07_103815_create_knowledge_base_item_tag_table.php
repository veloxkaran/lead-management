<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_item_tag', function (Blueprint $table) {
            $table->foreignId('knowledge_base_item_id')->constrained('knowledge_base_items')->cascadeOnDelete();
            $table->foreignId('knowledge_base_tag_id')->constrained('knowledge_base_tags')->cascadeOnDelete();
            $table->primary(['knowledge_base_item_id', 'knowledge_base_tag_id'], 'kb_item_tag_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_item_tag');
    }
};
