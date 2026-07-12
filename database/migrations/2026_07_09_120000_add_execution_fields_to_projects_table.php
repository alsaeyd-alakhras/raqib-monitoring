<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('execution_start_date')->nullable()->after('planned_end_date');
            $table->string('allocation_image_path')->nullable()->after('allocated_budget');
            $table->json('execution_region_names')->nullable()->after('execution_zones');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'execution_start_date',
                'allocation_image_path',
                'execution_region_names',
            ]);
        });
    }
};
