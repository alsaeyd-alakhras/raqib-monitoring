<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('passage_completed_by');
            $table->foreignId('rejected_by')->nullable()->after('rejection_reason')->constrained('users');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('gap_owner')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejection_reason', 'rejected_by', 'rejected_at', 'gap_owner']);
        });
    }
};
