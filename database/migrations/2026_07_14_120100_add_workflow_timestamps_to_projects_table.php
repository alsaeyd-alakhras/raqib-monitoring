<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('coordinator_filled_at')->nullable()->after('coordinator_filled_by');
            $table->timestamp('submitted_to_project_manager_at')->nullable()->after('coordinator_filled_at');
            $table->foreignId('submitted_to_project_manager_by')->nullable()->after('submitted_to_project_manager_at')->constrained('users');
            $table->timestamp('submitted_to_section_manager_at')->nullable()->after('submitted_to_project_manager_by');
            $table->foreignId('submitted_to_section_manager_by')->nullable()->after('submitted_to_section_manager_at')->constrained('users');
            $table->timestamp('monitor_submitted_at')->nullable()->after('monitoring_manager_received_by');
            $table->foreignId('monitor_submitted_by')->nullable()->after('monitor_submitted_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['submitted_to_project_manager_by']);
            $table->dropForeign(['submitted_to_section_manager_by']);
            $table->dropForeign(['monitor_submitted_by']);
            $table->dropColumn([
                'coordinator_filled_at',
                'submitted_to_project_manager_at',
                'submitted_to_project_manager_by',
                'submitted_to_section_manager_at',
                'submitted_to_section_manager_by',
                'monitor_submitted_at',
                'monitor_submitted_by',
            ]);
        });
    }
};
