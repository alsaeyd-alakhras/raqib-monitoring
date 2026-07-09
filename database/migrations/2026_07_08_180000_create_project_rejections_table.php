<?php

use App\Models\Project;
use App\Models\ProjectRejection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('rejection_reason');
            $table->string('gap_owner');
            $table->string('return_target')->nullable();
            $table->foreignId('return_target_person_id')->nullable()->constrained('people');
            $table->string('workflow_status_before')->nullable();
            $table->string('workflow_status_after');
            $table->foreignId('rejected_by')->constrained('users');
            $table->timestamp('rejected_at');
            $table->timestamps();
        });

        Project::query()
            ->whereNotNull('rejection_reason')
            ->whereNotNull('rejected_at')
            ->each(function (Project $project) {
                ProjectRejection::create([
                    'project_id' => $project->id,
                    'rejection_reason' => $project->rejection_reason,
                    'gap_owner' => $project->gap_owner ?? 'other',
                    'return_target' => $project->return_target,
                    'return_target_person_id' => $project->personIdForReturnTarget($project->return_target),
                    'workflow_status_before' => null,
                    'workflow_status_after' => $project->workflow_status,
                    'rejected_by' => $project->rejected_by,
                    'rejected_at' => $project->rejected_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_rejections');
    }
};
