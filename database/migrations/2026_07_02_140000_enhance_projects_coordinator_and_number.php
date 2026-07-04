<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('coordinator_external_name')->nullable()->after('coordinator_id');
            $table->foreignId('coordinator_filled_by')->nullable()->after('coordinator_submitted_by')->constrained('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE projects MODIFY project_number VARCHAR(50) NULL');

        DB::table('projects')
            ->whereNotNull('project_number')
            ->where('project_number', 'not like', 'P-%')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('projects')
                    ->where('id', $row->id)
                    ->update(['project_number' => 'P-' . $row->project_number]);
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coordinator_filled_by');
            $table->dropColumn('coordinator_external_name');
        });

        DB::table('projects')
            ->where('project_number', 'like', 'P-%')
            ->orderBy('id')
            ->each(function ($row) {
                $numeric = (int) substr((string) $row->project_number, 2);
                DB::table('projects')
                    ->where('id', $row->id)
                    ->update(['project_number' => $numeric ?: null]);
            });

        DB::statement('ALTER TABLE projects MODIFY project_number INT NULL');
    }
};
