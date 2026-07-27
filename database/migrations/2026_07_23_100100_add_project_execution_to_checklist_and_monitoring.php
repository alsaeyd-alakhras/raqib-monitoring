<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->foreignId('project_execution_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_executions')
                ->cascadeOnDelete();
        });

        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->foreignId('project_execution_id')
                ->nullable()
                ->after('source_id')
                ->constrained('project_executions')
                ->nullOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE monitoring_activities MODIFY source_type ENUM('project', 'external', 'meeting', 'project_execution') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_execution_id');
        });

        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_execution_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE monitoring_activities MODIFY source_type ENUM('project', 'external', 'meeting') NOT NULL");
        }
    }
};
