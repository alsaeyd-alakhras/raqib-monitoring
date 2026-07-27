<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_executions', function (Blueprint $table) {
            $table->foreign('primary_monitoring_activity_id')
                ->references('id')
                ->on('monitoring_activities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_executions', function (Blueprint $table) {
            $table->dropForeign(['primary_monitoring_activity_id']);
        });
    }
};
