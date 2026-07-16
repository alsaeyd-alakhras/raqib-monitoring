<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('secretariat_submitted_at')->nullable()->after('coordinator_submitted_by');
            $table->foreignId('secretariat_submitted_by')->nullable()->after('secretariat_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('secretariat_filled_at')->nullable()->after('secretariat_submitted_by');
            $table->foreignId('secretariat_filled_by')->nullable()->after('secretariat_filled_at')->constrained('users')->nullOnDelete();
        });

        $this->refreshPeopleRoleCheck();
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secretariat_filled_by');
            $table->dropColumn('secretariat_filled_at');
            $table->dropConstrainedForeignId('secretariat_submitted_by');
            $table->dropColumn('secretariat_submitted_at');
        });

        $rolesWithoutSecretariat = array_values(array_filter(
            Person::ROLES,
            fn (string $role) => $role !== 'project_secretariat'
        ));

        $this->refreshPeopleRoleCheck($rolesWithoutSecretariat);
    }

    /** @param list<string>|null $roles */
    private function refreshPeopleRoleCheck(?array $roles = null): void
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

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $roles ??= Person::ROLES;
        $roleList = implode("','", $roles);
        DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IS NULL OR role IN ('{$roleList}'))");
    }
};
