<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the template the Announcements module sends — additive only
 * (insertOrIgnore), so it never touches the four templates already
 * seeded/edited on a live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'announcement',
            'name' => 'Announcement',
            'subject' => '{{title}}',
            'body' => "Hi {{contact_person}},\n\n{{content}}\n\nThanks,\n{{app_name}}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'announcement')->delete();
    }
};
