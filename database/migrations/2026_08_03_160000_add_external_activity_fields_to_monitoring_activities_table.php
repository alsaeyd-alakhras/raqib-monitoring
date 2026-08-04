<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->string('detail')->nullable()->after('activity_type');
            $table->date('closure_date')->nullable()->after('action_taken');
            $table->json('attachments')->nullable()->after('closure_date');
            $table->text('positive_notes')->nullable()->after('attachments');
            $table->text('negative_notes')->nullable()->after('positive_notes');
            $table->text('recommendations')->nullable()->after('negative_notes');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_activities', function (Blueprint $table) {
            $table->dropColumn([
                'detail',
                'closure_date',
                'attachments',
                'positive_notes',
                'negative_notes',
                'recommendations',
            ]);
        });
    }
};
