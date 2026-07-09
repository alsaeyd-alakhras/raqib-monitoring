<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('procurement_rep');
            $table->foreignId('procurement_rep_id')->nullable()->after('funder_id')->constrained('people');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['procurement_rep_id']);
            $table->dropColumn('procurement_rep_id');
            $table->string('procurement_rep')->nullable()->after('funder_id');
        });
    }
};
