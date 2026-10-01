<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "html" for email bodies written in the rich-text editor; "text"
        // for SMS and for email campaigns created before the editor.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('message_format', 10)->default('text')->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('message_format');
        });
    }
};
