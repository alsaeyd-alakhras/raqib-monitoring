<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('region_key', 64);
            $table->string('region_name');
            $table->unsignedInteger('region_beneficiaries')->nullable();
            $table->string('region_execution_site', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('coordinator_id')->nullable()->constrained('people');
            $table->string('coordinator_external_name')->nullable();

            $table->foreignId('monitor_person_id')->nullable()->constrained('people');
            $table->date('monitoring_date')->nullable();
            $table->string('monitoring_method')->nullable();
            $table->string('monitoring_stage')->nullable();

            $table->decimal('coordinator_readiness_pct', 5, 2)->nullable();
            $table->decimal('monitor_readiness_pct', 5, 2)->nullable();
            $table->json('monitor_notes')->nullable();
            $table->json('monitor_negative_notes')->nullable();
            $table->json('monitor_recommendations')->nullable();

            $table->string('workflow_status')->default('pending_coordinator');
            $table->unsignedBigInteger('primary_monitoring_activity_id')->nullable();

            $table->timestamp('coordinator_submitted_at')->nullable();
            $table->foreignId('coordinator_submitted_by')->nullable()->constrained('users');
            $table->timestamp('coordinator_filled_at')->nullable();
            $table->foreignId('coordinator_filled_by')->nullable()->constrained('users');
            $table->timestamp('submitted_to_project_manager_at')->nullable();
            $table->foreignId('submitted_to_project_manager_by')->nullable()->constrained('users');
            $table->timestamp('submitted_to_section_manager_at')->nullable();
            $table->foreignId('submitted_to_section_manager_by')->nullable()->constrained('users');
            $table->timestamp('section_manager_approved_at')->nullable();
            $table->foreignId('section_manager_approved_by')->nullable()->constrained('users');
            $table->timestamp('dept_manager_approved_at')->nullable();
            $table->foreignId('dept_manager_approved_by')->nullable()->constrained('users');
            $table->timestamp('monitoring_manager_received_at')->nullable();
            $table->foreignId('monitoring_manager_received_by')->nullable()->constrained('users');
            $table->timestamp('monitor_submitted_at')->nullable();
            $table->foreignId('monitor_submitted_by')->nullable()->constrained('users');

            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->string('gap_owner')->nullable();
            $table->string('return_target')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['project_id', 'region_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_executions');
    }
};
