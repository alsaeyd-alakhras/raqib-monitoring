<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('execution_regions')->nullable()->after('execution_zones');
            $table->foreignId('currency_id')->nullable()->after('estimated_duration')->constrained('currencies')->nullOnDelete();
            $table->decimal('project_budget', 15, 2)->nullable()->after('currency_id');
            $table->decimal('revenue_amount', 15, 2)->nullable()->after('project_budget');
            $table->decimal('net_amount', 15, 2)->nullable()->after('revenue_amount');
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('net_amount');
            $table->decimal('execution_amount_ils', 15, 2)->nullable()->after('exchange_rate');
        });

        DB::table('projects')
            ->whereNotNull('execution_region_names')
            ->orderBy('id')
            ->each(function (object $project) {
                $names = json_decode((string) $project->execution_region_names, true);

                if (! is_array($names) || $names === []) {
                    return;
                }

                $regions = array_map(
                    fn ($name) => [
                        'name' => trim((string) $name),
                        'beneficiaries' => null,
                    ],
                    array_values($names)
                );

                DB::table('projects')->where('id', $project->id)->update([
                    'execution_regions' => json_encode($regions, JSON_UNESCAPED_UNICODE),
                ]);
            });

        DB::table('projects')
            ->whereNotNull('allocated_budget')
            ->whereNull('execution_amount_ils')
            ->update([
                'execution_amount_ils' => DB::raw('allocated_budget'),
            ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['execution_region_names', 'allocated_budget']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('execution_region_names')->nullable()->after('execution_zones');
            $table->decimal('allocated_budget', 15, 2)->nullable()->after('estimated_duration');
        });

        DB::table('projects')
            ->whereNotNull('execution_regions')
            ->orderBy('id')
            ->each(function (object $project) {
                $regions = json_decode((string) $project->execution_regions, true);

                if (! is_array($regions) || $regions === []) {
                    return;
                }

                $names = array_map(
                    fn ($region) => is_array($region) ? ($region['name'] ?? '') : (string) $region,
                    $regions
                );

                DB::table('projects')->where('id', $project->id)->update([
                    'execution_region_names' => json_encode(array_values($names), JSON_UNESCAPED_UNICODE),
                ]);
            });

        DB::table('projects')
            ->whereNotNull('execution_amount_ils')
            ->whereNull('allocated_budget')
            ->update([
                'allocated_budget' => DB::raw('execution_amount_ils'),
            ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn([
                'execution_regions',
                'currency_id',
                'project_budget',
                'revenue_amount',
                'net_amount',
                'exchange_rate',
                'execution_amount_ils',
            ]);
        });
    }
};
