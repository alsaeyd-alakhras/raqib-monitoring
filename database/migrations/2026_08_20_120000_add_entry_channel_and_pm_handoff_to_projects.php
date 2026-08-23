<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('entry_channel', 32)->default('project_manager')->after('created_by');
            $table->timestamp('handed_to_pm_at')->nullable()->after('entry_channel');
            $table->foreignId('handed_to_pm_by')->nullable()->after('handed_to_pm_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_to_pm_by');
            $table->dropColumn(['handed_to_pm_at', 'entry_channel']);
        });
    }
};
