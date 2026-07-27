<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['checklist_item_id']);
            $table->dropUnique(['project_id', 'checklist_item_id']);
            $table->unique(['project_id', 'project_execution_id', 'checklist_item_id'], 'pcv_project_execution_item_unique');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('checklist_item_id')->references('id')->on('checklist_items');
        });
    }

    public function down(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['checklist_item_id']);
            $table->dropUnique('pcv_project_execution_item_unique');
            $table->unique(['project_id', 'checklist_item_id']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('checklist_item_id')->references('id')->on('checklist_items');
        });
    }
};
