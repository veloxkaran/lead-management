<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Team org-grouping concept entirely, per explicit user request.
 * Confirmed via a live-data check before writing this migration: the `teams`
 * table had zero rows and `team_id` was unset on every user/goal/meeting —
 * this is dropping unused scaffolding, not live-traffic-bearing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('goal_type')->constrained()->nullOnDelete();
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('participants')->constrained()->nullOnDelete();
        });
    }
};
