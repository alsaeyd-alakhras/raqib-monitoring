<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->string('attachment_type', 10)->default('file')->after('attachment_uploaded_at');
            $table->string('attachment_url', 2048)->nullable()->after('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->dropColumn(['attachment_type', 'attachment_url']);
        });
    }
};
