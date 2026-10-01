<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Super Admin approval of campaigns created by everyone else.
        // reviewed_by is a plain indexed column, not a foreign key: SQLite can
        // only add a foreign key by rebuilding the whole campaigns table.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('created_by')->index();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
