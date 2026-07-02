<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->change();
            $table->foreignId('department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable(false)->change();
            $table->foreignId('department_id')->nullable(false)->change();
        });
    }
};
