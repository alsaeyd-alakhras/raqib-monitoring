<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropPeopleRoleCheck();

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE people MODIFY role VARCHAR(255) NULL');

        $roles = implode("','", Person::ROLES);
        DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IS NULL OR role IN ('{$roles}'))");
    }

    public function down(): void
    {
        $this->dropPeopleRoleCheck();

        DB::table('people')->whereNull('role')->update(['role' => 'admin']);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE people MODIFY role VARCHAR(255) NOT NULL');

        $roles = implode("','", Person::ROLES);
        DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IN ('{$roles}'))");
    }

    private function dropPeopleRoleCheck(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE people DROP CHECK chk_people_role');
        } catch (\Throwable) {
            try {
                DB::statement('ALTER TABLE people DROP CONSTRAINT chk_people_role');
            } catch (\Throwable) {
                // SQLite or databases without the named constraint.
            }
        }
    }
};
