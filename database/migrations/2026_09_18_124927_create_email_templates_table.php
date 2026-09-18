<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }

    /**
     * Ships the four templates the app actually sends out of the box —
     * seeded here (not via a separate db:seed run) so a fresh migrate
     * always leaves the client-notification feature fully usable,
     * matching this app's existing seed_role_playbooks migration.
     * Super Admin can edit the subject/body afterward; the {{...}} merge
     * fields are documented on the Email Templates edit screen.
     */
    private function seedDefaults(): void
    {
        $now = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'requirement_created',
                'name' => 'Requirement Created',
                'subject' => 'New requirement logged for {{company_name}}',
                'body' => "Hi {{contact_person}},\n\nA new requirement has been logged for {{company_name}}:\n\n\"{{requirement}}\"\n\nPriority: {{priority}}\n\nWe'll keep you updated as it progresses.\n\nThanks,\n{{app_name}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'requirement_status_changed',
                'name' => 'Requirement Status Changed',
                'subject' => 'Requirement update for {{company_name}}',
                'body' => "Hi {{contact_person}},\n\nThe status of your requirement \"{{requirement}}\" has changed from {{old_status}} to {{new_status}}.\n\nThanks,\n{{app_name}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'support_ticket_created',
                'name' => 'Support Ticket Created',
                'subject' => 'Support ticket received: {{subject}}',
                'body' => "Hi {{contact_person}},\n\nWe've received your support ticket:\n\n\"{{subject}}\"\n\nPriority: {{priority}}\n\nOur team will be in touch shortly.\n\nThanks,\n{{app_name}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'support_ticket_status_changed',
                'name' => 'Support Ticket Status Changed',
                'subject' => 'Support ticket update: {{subject}}',
                'body' => "Hi {{contact_person}},\n\nThe status of your support ticket \"{{subject}}\" has changed from {{old_status}} to {{new_status}}.\n\nThanks,\n{{app_name}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};
