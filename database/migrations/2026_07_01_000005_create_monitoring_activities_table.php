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
        Schema::create('monitoring_activities', function (Blueprint $table) {
            $table->id();

            $table->string('reference_code')->unique();
            $table->enum('source_type', ['project', 'external', 'meeting']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->enum('activity_role', ['primary', 'secondary'])->default('primary');

            $table->foreignId('center_id')->constrained('centers');
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('section_id')->nullable()->constrained('sections');
            $table->foreignId('responsible_person_id')->nullable()->constrained('people');
            $table->foreignId('monitor_person_id')->nullable()->constrained('people');

            $table->date('activity_date')->nullable();
            $table->time('activity_time')->nullable();

            $table->string('activity_type')->nullable();
            $table->foreignId('funder_id')->nullable()->constrained('funders');

            $table->text('subject')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('field_problem')->default(false);
            $table->text('action_taken')->nullable();

            $table->decimal('execution_value', 5, 2)->nullable();
            $table->decimal('quality_value', 5, 2)->nullable();
            $table->decimal('closure_value', 5, 2)->nullable();
            $table->decimal('deduction_value', 5, 2)->nullable();

            $table->decimal('kpi_value', 5, 2)->nullable();
            $table->string('kpi_rating')->nullable();

            $table->string('monitoring_method')->nullable();
            $table->string('monitoring_stage')->nullable();
            $table->string('workflow_status')->default('pending_monitor');
            $table->boolean('is_passage_complete')->default(false);
            $table->timestamp('passage_completed_at')->nullable();
            $table->foreignId('passage_completed_by')->nullable()->constrained('users');

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_activities');
    }
};
