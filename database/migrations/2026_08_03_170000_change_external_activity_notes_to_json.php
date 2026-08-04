<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->dropColumn(['positive_notes', 'negative_notes', 'recommendations']);
        });

        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->json('positive_notes')->nullable()->after('attachments');
            $table->json('negative_notes')->nullable()->after('positive_notes');
            $table->json('recommendations')->nullable()->after('negative_notes');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->dropColumn(['positive_notes', 'negative_notes', 'recommendations']);
        });

        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->text('positive_notes')->nullable()->after('attachments');
            $table->text('negative_notes')->nullable()->after('positive_notes');
            $table->text('recommendations')->nullable()->after('negative_notes');
        });
    }
};
