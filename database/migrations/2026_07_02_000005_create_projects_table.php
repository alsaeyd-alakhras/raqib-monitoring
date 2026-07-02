<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // === project basic data ===
            $table->string('project_name');
            $table->integer('project_number')->nullable();
            $table->string('project_type')->nullable();
            $table->foreignId('funder_id')->nullable()->constrained('funders');
            $table->string('procurement_rep')->nullable();
            $table->foreignId('project_manager_id')->constrained('people');
            $table->foreignId('coordinator_id')->nullable()->constrained('people');
            $table->foreignId('center_id')->nullable()->constrained('centers');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('section_id')->nullable()->constrained('sections');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->text('location')->nullable();

            // === execution data ===
            $table->integer('target_beneficiaries')->nullable();
            $table->integer('execution_zones')->nullable();
            $table->string('estimated_duration')->nullable();
            $table->decimal('allocated_budget', 15, 2)->nullable();

            // === field monitor data (filled during monitoring phase) ===
            $table->foreignId('monitor_person_id')->nullable()->constrained('people');
            $table->date('monitoring_date')->nullable();
            $table->string('monitoring_method')->nullable();
            $table->string('monitoring_stage')->nullable();

            // === checklist readiness results (computed, stored for performance) ===
            $table->decimal('coordinator_readiness_pct', 5, 2)->nullable();
            $table->decimal('monitor_readiness_pct', 5, 2)->nullable();

            // === monitor notes/recommendations (JSON arrays of strings) ===
            $table->json('monitor_notes')->nullable();
            $table->json('monitor_recommendations')->nullable();

            // === workflow & signatures ===
            $table->string('workflow_status')->default('draft');
            $table->foreignId('primary_monitoring_activity_id')->nullable()->constrained('monitoring_activities');
            $table->timestamp('coordinator_submitted_at')->nullable();
            $table->foreignId('coordinator_submitted_by')->nullable()->constrained('users');
            $table->timestamp('dept_manager_approved_at')->nullable();
            $table->foreignId('dept_manager_approved_by')->nullable()->constrained('users');
            $table->timestamp('monitoring_manager_received_at')->nullable();
            $table->foreignId('monitoring_manager_received_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->string('gap_owner')->nullable();

            // === system fields ===
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('project_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
