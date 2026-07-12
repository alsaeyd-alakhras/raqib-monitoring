<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('person_name');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
            $table->timestamp('attachment_uploaded_at')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('project_checklist_values', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path',
                'attachment_original_name',
                'attachment_uploaded_at',
            ]);
        });
    }
};
