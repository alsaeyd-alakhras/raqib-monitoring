<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('coordinator_requirements')->nullable()->after('estimated_duration');
            $table->text('project_lifecycle_notes')->nullable()->after('coordinator_requirements');
            $table->text('pm_recommendations')->nullable()->after('project_lifecycle_notes');
            $table->text('implementation_mechanism')->nullable()->after('pm_recommendations');
        });

        Schema::table('project_executions', function (Blueprint $table) {
            $table->string('nomination_responsibility', 32)->nullable()->after('coordinator_external_name');
            $table->text('implementation_mechanism')->nullable()->after('nomination_responsibility');
        });
    }

    public function down(): void
    {
        Schema::table('project_executions', function (Blueprint $table) {
            $table->dropColumn(['nomination_responsibility', 'implementation_mechanism']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'coordinator_requirements',
                'project_lifecycle_notes',
                'pm_recommendations',
                'implementation_mechanism',
            ]);
        });
    }
};
