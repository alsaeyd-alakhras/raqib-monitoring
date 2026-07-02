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
        Schema::create('project_checklist_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('checklist_items');
            $table->enum('coordinator_value', ['ready', 'partial', 'not_ready', 'not_required'])->nullable();
            $table->enum('monitor_value', ['ready', 'partial', 'not_ready', 'not_required'])->nullable();
            $table->string('person_name')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'checklist_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_checklist_values');
    }
};
