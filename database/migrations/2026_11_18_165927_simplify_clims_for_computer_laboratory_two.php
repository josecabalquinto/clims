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
        $now = now();
        $locationId = DB::table('equipment_locations')->where('name', 'Computer Lab 2')->value('id');

        if ($locationId === null) {
            $locationId = DB::table('equipment_locations')->insertGetId([
                'name' => 'Computer Lab 2',
                'description' => 'The laboratory managed by CLIMS.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('equipment_locations')->where('id', $locationId)->update([
            'is_active' => true,
            'updated_at' => $now,
        ]);
        DB::table('equipment_locations')->where('id', '!=', $locationId)->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);
        DB::table('equipment')->update(['location_id' => $locationId, 'updated_at' => $now]);

        DB::table('users')->where('role', '!=', 'super_admin')->update([
            'role' => 'super_admin',
            'updated_at' => $now,
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE borrow_transactions MODIFY borrower_type ENUM('student', 'teacher', 'instructor', 'staff', 'other') NOT NULL");
        }

        DB::table('borrow_transactions')
            ->whereIn('borrower_type', ['instructor', 'staff', 'other'])
            ->update(['borrower_type' => 'teacher', 'updated_at' => $now]);

        Schema::table('borrow_transactions', function (Blueprint $table) {
            $table->string('grade_level')->nullable()->after('borrower_identifier');
            $table->string('section')->nullable()->after('grade_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrow_transactions', function (Blueprint $table) {
            $table->dropColumn(['grade_level', 'section']);
        });

        // Account roles and location assignments are intentionally not restored:
        // their previous values cannot be reconstructed without risking data loss.
    }
};
