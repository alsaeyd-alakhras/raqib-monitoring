<?php

use App\Models\Person;
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
        if (! Schema::hasColumn('people', 'role')) {
            Schema::table('people', function (Blueprint $table) {
                $table->string('role')->nullable()->after('name');
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        // صفوف موجودة قبل إضافة الدور — تعيين مؤقت حتى يعدّلها الأدمن
        DB::table('people')
            ->where(function ($query) {
                $query->whereNull('role')->orWhere('role', '');
            })
            ->update(['role' => 'admin']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE people MODIFY role VARCHAR(255) NOT NULL');

            $roles = implode("','", Person::ROLES);
            DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IN ('{$roles}'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE people DROP CHECK chk_people_role');
        } catch (\Throwable) {
            try {
                DB::statement('ALTER TABLE people DROP CONSTRAINT chk_people_role');
            } catch (\Throwable) {
                // SQLite or databases without the named constraint.
            }
        }

        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['role', 'department_id']);
        });
    }
};
