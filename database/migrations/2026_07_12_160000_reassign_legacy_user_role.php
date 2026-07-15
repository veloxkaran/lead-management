<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles are now fixed: super_admin, manager, business_development,
     * customer_success, finance. The old generic 'user' role is retired —
     * every account carrying it today was, in practice, doing Business
     * Development work (this app's original single purpose), so that's
     * where they land. An admin can reassign individuals afterward.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'user')->update(['role' => 'business_development']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'business_development')->update(['role' => 'user']);
    }
};
