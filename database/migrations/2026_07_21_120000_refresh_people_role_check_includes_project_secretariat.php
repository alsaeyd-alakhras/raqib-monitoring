<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshPeopleRoleCheck();
    }

    public function down(): void
    {
        // لا نرجّع قائمة أدوار أقدم — القيد يبقى مطابقاً لـ Person::ROLES
    }

    private function refreshPeopleRoleCheck(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        try {
            DB::statement('ALTER TABLE people DROP CHECK chk_people_role');
        } catch (\Throwable) {
            try {
                DB::statement('ALTER TABLE people DROP CONSTRAINT chk_people_role');
            } catch (\Throwable) {
                //
            }
        }

        $roleList = implode("','", Person::ROLES);
        DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IS NULL OR role IN ('{$roleList}'))");
    }
};
