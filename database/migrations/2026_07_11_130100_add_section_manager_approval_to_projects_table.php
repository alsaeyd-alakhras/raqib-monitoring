<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('section_manager_approved_at')->nullable()->after('coordinator_filled_by');
            $table->foreignId('section_manager_approved_by')->nullable()->after('section_manager_approved_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['section_manager_approved_by']);
            $table->dropColumn(['section_manager_approved_at', 'section_manager_approved_by']);
        });
    }
};
