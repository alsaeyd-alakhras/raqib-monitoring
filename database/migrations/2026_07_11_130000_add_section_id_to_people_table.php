<?php

use App\Models\Person;
use App\Models\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('people', 'section_id')) {
            Schema::table('people', function (Blueprint $table) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('sections')
                    ->nullOnDelete();
            });
        }

        $this->dropPeopleRoleCheck();

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $roles = implode("','", Person::ROLES);
            DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IN ('{$roles}'))");
        }

        $sectionIdsByDepartment = Section::query()
            ->orderBy('id')
            ->get()
            ->groupBy('department_id')
            ->map(fn ($sections) => $sections->first()?->id);

        DB::table('people')
            ->whereIn('role', ['project_manager', 'coordinator'])
            ->whereNull('section_id')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->each(function ($person) use ($sectionIdsByDepartment) {
                $sectionId = $sectionIdsByDepartment->get($person->department_id);

                if ($sectionId) {
                    DB::table('people')
                        ->where('id', $person->id)
                        ->update(['section_id' => $sectionId]);
                }
            });
    }

    public function down(): void
    {
        $this->dropPeopleRoleCheck();

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $rolesWithoutSectionManager = array_filter(
                Person::ROLES,
                fn (string $role) => $role !== 'section_manager'
            );
            $roles = implode("','", $rolesWithoutSectionManager);
            DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IN ('{$roles}'))");
        }

        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }

    private function dropPeopleRoleCheck(): void
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
    }
};
