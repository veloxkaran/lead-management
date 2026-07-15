<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('department')->constrained()->nullOnDelete();
        });

        $this->backfillDepartmentsFromFreeText();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    /**
     * Promotes the free-text `users.department` string into real `departments`
     * rows and links each user to one, without touching or dropping the
     * original column — it simply stops being written to going forward.
     */
    private function backfillDepartmentsFromFreeText(): void
    {
        $users = DB::table('users')
            ->select('id', 'company_id', 'department')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->get();

        $departmentIdsByKey = [];

        foreach ($users as $user) {
            $key = ($user->company_id ?? 'null').'|'.mb_strtolower(trim($user->department));

            if (! isset($departmentIdsByKey[$key])) {
                $existing = DB::table('departments')
                    ->where('company_id', $user->company_id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($user->department))])
                    ->first();

                $departmentIdsByKey[$key] = $existing?->id ?? DB::table('departments')->insertGetId([
                    'company_id' => $user->company_id,
                    'name' => trim($user->department),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')->where('id', $user->id)->update([
                'department_id' => $departmentIdsByKey[$key],
            ]);
        }
    }
};
