<?php

namespace Tests\Feature\Concerns;

use App\Models\ActivityLog;
use App\Models\Center;
use App\Models\Constant;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Funder;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\ProjectExecution;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait InteractsWithRaqibProjects
{
    protected function secretariatUserForDepartment(int $departmentId): User
    {
        $user = User::where('username', 'sec_hana')->first();
        if (! $user) {
            $this->artisan('db:seed', ['--class' => 'DemoUsersSeeder']);
            $user = User::where('username', 'sec_hana')->firstOrFail();
        }

        $user->person?->update([
            'department_id' => $departmentId,
            'role' => 'project_secretariat',
            'phone' => $user->person?->phone ?: '0599000111',
        ]);

        $this->syncUserAbilities($user, ['projects.view', 'projects.create', 'projects.update']);

        return $user->fresh(['person']);
    }

    protected function nextProjectNumberSeq(): int
    {
        return Project::sequenceFromProjectNumber(Project::generateProjectNumber()) ?? 1;
    }

    /** @return array<string, mixed> */
    protected function sampleProjectFields(array $overrides = []): array
    {
        $center = Center::firstOrFail();
        $department = Department::where('center_id', $center->id)->firstOrFail();
        $section = Section::where('department_id', $department->id)->first()
            ?? Section::create([
                'department_id' => $department->id,
                'name' => 'قسم تجريبي',
            ]);

        $pm = Person::withRole('project_manager')
            ->where('section_id', $section->id)
            ->first()
            ?? Person::withRole('project_manager')->first()
            ?? Person::first();

        $this->alignPersonToSection($pm, $section);

        $funder = Funder::first()
            ?? Funder::create(['name' => 'ممول تجريبي']);
        $procurementRep = Person::firstOrFail();
        $projectTypes = json_decode((string) Constant::where('key', 'project_types')->value('value'), true);
        $associationOffices = json_decode((string) Constant::where('key', 'association_offices')->value('value'), true);
        if (! is_array($associationOffices) || $associationOffices === []) {
            $associationOffices = ['مكتب غزة', 'مكتب خانيونس'];
            Constant::updateOrCreate(
                ['key' => 'association_offices'],
                ['value' => json_encode($associationOffices, JSON_UNESCAPED_UNICODE)]
            );
        }
        $currency = Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'دولار', 'value' => 1, 'value_to_ils' => 3.70]
        );
        $coordinator = Person::withRole('coordinator')->firstOrFail();

        return array_merge([
            'project_manager_id' => $pm->id,
            'project_type' => is_array($projectTypes) ? ($projectTypes[0] ?? 'مشروع') : 'مشروع',
            'funder_id' => $funder->id,
            'procurement_rep_id' => $procurementRep->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'section_id' => $section->id,
            'planned_start_date' => '2026-01-01',
            'planned_end_date' => '2026-06-30',
            'execution_start_date' => '2026-02-01',
            'target_beneficiaries' => 100,
            'execution_zones' => 2,
            'execution_regions' => [
                [
                    'name' => $associationOffices[0],
                    'beneficiaries' => 40,
                    'execution_site' => 'موقع 1',
                    'coordinator_mode' => 'person',
                    'coordinator_id' => $coordinator->id,
                ],
                [
                    'name' => $associationOffices[1] ?? $associationOffices[0],
                    'beneficiaries' => 60,
                    'coordinator_mode' => 'self',
                ],
            ],
            'estimated_duration' => '6 أشهر',
            'currency_id' => $currency->id,
            'project_budget' => 50000,
            'revenue_amount' => 5000,
            'net_amount' => 45000,
            'exchange_rate' => 3.70,
            'execution_amount_ils' => 166500,
        ], $overrides);
    }

    protected function alignPersonToSection(Person $person, Section $section): void
    {
        if ((int) $person->section_id !== (int) $section->id) {
            $person->update([
                'section_id' => $section->id,
                'department_id' => $section->department_id,
            ]);
        }
    }

    /** @param  list<string>  $abilities
     * @return array{0: User, 1: Person}
     */
    protected function createEphemeralProjectManager(array $abilities, ?string $suffix = null): array
    {
        $suffix = $suffix ?? uniqid();
        $section = Section::firstOrFail();

        $user = User::create([
            'name' => 'مدير مشروع اختبار ' . $suffix,
            'username' => 'pm_test_' . $suffix,
            'email' => 'pm_test_' . $suffix . '@test.local',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
            'password' => bcrypt('password'),
        ]);

        $person = Person::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => 'project_manager',
            'department_id' => $section->department_id,
            'section_id' => $section->id,
            'job_title' => 'مدير مشروع اختبار',
            'phone' => '0599' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        ]);

        $this->syncUserAbilities($user, $abilities);

        return [$user, $person];
    }

    /** @param  list<string>  $abilities */
    protected function syncUserAbilities(User $user, array $abilities): void
    {
        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($abilities) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }

    protected function assignProjectAllocation(Project $project, ?int $seq = null): void
    {
        $seq ??= $this->nextProjectNumberSeq() + random_int(10000, 99999);
        $projectNumber = Project::formatFromSequence($seq);
        $path = 'projects/' . $projectNumber . '/allocation.jpg';

        Storage::disk('public')->put($path, 'fake-image');

        $project->update([
            'project_number' => $projectNumber,
            'allocation_image_path' => $path,
        ]);
    }

    protected function deleteEphemeralUser(User $user): void
    {
        $personId = Person::where('user_id', $user->id)->value('id');

        if ($personId) {
            $projectIds = Project::where('project_manager_id', $personId)->pluck('id');
            if ($projectIds->isNotEmpty()) {
                ProjectChecklistValue::whereIn('project_id', $projectIds)->delete();
                ProjectExecution::whereIn('project_id', $projectIds)->delete();
                Project::whereIn('id', $projectIds)->delete();
            }

            Person::where('user_id', $user->id)->delete();
        }

        RoleUser::where('user_id', $user->id)->delete();
        ActivityLog::where('user_id', $user->id)->delete();

        User::withoutEvents(function () use ($user) {
            $user->delete();
        });
    }

    protected function assertPageContainsText($response, string $text): void
    {
        $content = $response->getContent();
        $candidates = [
            $text,
            json_encode($text, JSON_UNESCAPED_UNICODE),
            json_encode($text),
        ];

        $unicodeEscaped = '';
        $length = mb_strlen($text, 'UTF-8');
        for ($index = 0; $index < $length; $index++) {
            $unicodeEscaped .= sprintf('\\u%04x', mb_ord(mb_substr($text, $index, 1, 'UTF-8'), 'UTF-8'));
        }
        $candidates[] = $unicodeEscaped;

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && str_contains($content, $candidate)) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail("Expected page to contain: {$text}");
    }
}
