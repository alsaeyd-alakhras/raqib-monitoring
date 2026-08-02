<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class PromoteOrdinaryToCoordinators
{
    public function __construct(
        private readonly UserRoleAbilitiesSync $abilitiesSync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false): array
    {
        $candidates = $this->candidates()->get();
        $skipped = $this->skippedNoSection()->get();

        $promotedPeople = [];

        if (! $dryRun) {
            foreach ($candidates as $person) {
                $person->update(['role' => 'coordinator']);

                if ($person->user) {
                    $this->abilitiesSync->syncFromRole($person->user, 'coordinator');
                }

                $promotedPeople[] = $this->personSummary($person);
            }
        } else {
            $promotedPeople = $candidates
                ->map(fn (Person $person) => $this->personSummary($person))
                ->all();
        }

        $report = [
            'dry_run' => $dryRun,
            'promoted_count' => $candidates->count(),
            'skipped_no_section_count' => $skipped->count(),
            'promoted' => $promotedPeople,
            'skipped_no_section' => $skipped
                ->map(fn (Person $person) => $this->personSummary($person))
                ->all(),
            'run_at' => now()->toIso8601String(),
        ];

        if (! $dryRun) {
            $this->writeReport($report);
        }

        return $report;
    }

    public function candidates(): Builder
    {
        return Person::query()
            ->where(fn (Builder $query) => $query->whereNull('role')->orWhere('role', ''))
            ->whereNotNull('user_id')
            ->whereNotNull('section_id')
            ->whereHas('user', fn (Builder $query) => $query->where('super_admin', false))
            ->with(['user', 'section', 'department'])
            ->orderBy('id');
    }

    public function skippedNoSection(): Builder
    {
        return Person::query()
            ->where(fn (Builder $query) => $query->whereNull('role')->orWhere('role', ''))
            ->whereNotNull('user_id')
            ->whereNull('section_id')
            ->whereHas('user', fn (Builder $query) => $query->where('super_admin', false))
            ->with(['user'])
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function personSummary(Person $person): array
    {
        return [
            'person_id' => $person->id,
            'name' => $person->name,
            'username' => $person->user?->username,
            'section_id' => $person->section_id,
            'department_id' => $person->department_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(array $report): void
    {
        $path = storage_path(config('raqib.promote_coordinators_report_path'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
