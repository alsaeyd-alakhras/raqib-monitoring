<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterDataCleaner
{
    public function clean(bool $dryRun = false): void
    {
        if ($dryRun) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->deleteIfExists('project_checklist_values');
            $this->deleteIfExists('project_rejections');
            $this->deleteIfExists('projects');
            $this->deleteIfExists('monitoring_activities');
            $this->deleteIfExists('role_users');
            $this->deleteIfExists('people');
            DB::table('users')->where('super_admin', 0)->delete();
            $this->deleteIfExists('funders');
            $this->deleteIfExists('sections');
            $this->deleteIfExists('departments');
            $this->deleteIfExists('centers');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function deleteIfExists(string $table): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }
}
