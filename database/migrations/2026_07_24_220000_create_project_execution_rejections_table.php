<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_execution_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_execution_id')->constrained('project_executions')->cascadeOnDelete();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('project_execution_rejections');
    }
};
