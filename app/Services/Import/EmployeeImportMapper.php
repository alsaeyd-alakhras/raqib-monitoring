<?php

namespace App\Services\Import;

use App\Models\Center;
use App\Models\Department;
use App\Models\Section;
use App\Services\RoleAbilitiesService;
use Illuminate\Support\Collection;

class EmployeeImportMapper
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var Collection<string, Center> */
    private Collection $centersByName;

    /** @var Collection<int, Department> */
    private Collection $departments;

    /** @var Collection<int, Section> */
    private Collection $sections;

    /** @var Collection<string, Section> */
    private Collection $sectionsByName;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require base_path('data/employee-import-mappings.php');
        $this->loadOrganization();
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, report: array<string, mixed>}
     */
    public function mapRows(array $rows): array
    {
        $report = [
            'imported' => 0,
            'skipped' => 0,
            'without_section' => 0,
            'without_department' => 0,
            'without_role' => 0,
            'system_roles' => 0,
            'manager_conflicts' => [],
            'needs_review' => [],
            'skipped_rows' => [],
        ];

        $parsed = [];

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);

            if ($mapped['skip']) {
                $report['skipped']++;
                $report['skipped_rows'][] = [
                    'row' => $mapped['row'],
                    'reason' => $mapped['skip_reason'],
                ];
                continue;
            }

            $parsed[] = $mapped;
        }

        $resolved = $this->resolveManagerUniqueness($parsed, $report);

        foreach ($resolved as $item) {
            $report['imported']++;

            if ($item['section_id'] === null) {
                $report['without_section']++;
            }

            if ($item['department_id'] === null) {
                $report['without_department']++;
            }

            if ($item['role'] === null) {
                $report['without_role']++;
            } else {
                $report['system_roles']++;
            }

            if (! empty($item['review_notes'])) {
                $report['needs_review'][] = [
                    'row' => $item['row'],
                    'name' => $item['name'],
                    'national_id' => $item['national_id'],
                    'notes' => $item['review_notes'],
                ];
            }
        }

        return [
            'rows' => $resolved,
            'report' => $report,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $columns = $this->config['columns'];
        $name = trim($row[$columns['name']] ?? '');
        $nationalId = $this->normalizeNationalId($row[$columns['national_id']] ?? '');

        if ($name === '' || $nationalId === '') {
            return [
                'skip' => true,
                'row' => (int) ($row['_row'] ?? 0),
                'skip_reason' => 'اسم أو رقم هوية فارغ',
            ];
        }

        $legacySection = trim($row[$columns['section']] ?? '');
        $departmentInput = trim($row[$columns['department']] ?? '');
        $roleLabel = trim($row[$columns['role_label']] ?? '');
        $jobNature = trim($row[$columns['job_nature']] ?? '');
        $workplace = trim($row[$columns['workplace']] ?? '');

        $centerName = $this->resolveCenterName($legacySection);
        $center = $this->centersByName->get($centerName);

        $department = $this->resolveDepartment($departmentInput, $legacySection, $workplace, $center?->id);
        $section = $this->resolveSection($legacySection, $jobNature, $workplace, $department);

        $role = $this->resolveRole($roleLabel, $jobNature, $legacySection, $department?->name);

        $reviewNotes = [];

        if ($department && $section && (int) $section->department_id !== (int) $department->id) {
            $section = null;
            $reviewNotes[] = 'القسم لا يتبع الدائرة المستنتجة';
        }

        if ($role === 'monitor') {
            $monitoringDepartment = $this->findDepartmentByName($this->config['monitoring_department'], $this->centerId('association'));
            $department = $monitoringDepartment;
            $section = null;
        }

        return [
            'skip' => false,
            'row' => (int) ($row['_row'] ?? 0),
            'name' => $name,
            'national_id' => $nationalId,
            'job_title' => $jobNature,
            'role' => $role,
            'candidate_role' => $role,
            'role_label' => $roleLabel,
            'department_id' => $department?->id,
            'section_id' => $section?->id,
            'center_name' => $centerName,
            'legacy_section' => $legacySection,
            'workplace' => $workplace,
            'priority_score' => $this->managerPriorityScore($role, $roleLabel),
            'review_notes' => $reviewNotes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsed
     * @return array<int, array<string, mixed>>
     */
    private function resolveManagerUniqueness(array $parsed, array &$report): array
    {
        $claimedDepartments = [];
        $claimedSections = [];
        $monitoringDirectorClaimed = false;

        usort($parsed, fn (array $a, array $b) => ($b['priority_score'] <=> $a['priority_score']) ?: ($a['row'] <=> $b['row']));

        foreach ($parsed as &$item) {
            $role = $item['candidate_role'];

            if ($role === 'department_manager' && $item['department_id']) {
                if (isset($claimedDepartments[$item['department_id']])) {
                    $item['role'] = null;
                    $report['manager_conflicts'][] = [
                        'row' => $item['row'],
                        'name' => $item['name'],
                        'type' => 'department_manager',
                        'department_id' => $item['department_id'],
                        'winner' => $claimedDepartments[$item['department_id']],
                    ];
                    $item['review_notes'][] = 'تم إلغاء دور مدير الدائرة بسبب وجود مسؤول مسبق';
                } else {
                    $claimedDepartments[$item['department_id']] = $item['name'];
                }
            }

            if ($role === 'section_manager' && $item['section_id']) {
                if (isset($claimedSections[$item['section_id']])) {
                    $item['role'] = null;
                    $report['manager_conflicts'][] = [
                        'row' => $item['row'],
                        'name' => $item['name'],
                        'type' => 'section_manager',
                        'section_id' => $item['section_id'],
                        'winner' => $claimedSections[$item['section_id']],
                    ];
                    $item['review_notes'][] = 'تم إلغاء دور مدير القسم بسبب وجود مسؤول مسبق';
                } else {
                    $claimedSections[$item['section_id']] = $item['name'];
                }
            }

            if ($role === 'monitoring_director') {
                if ($monitoringDirectorClaimed) {
                    $item['role'] = null;
                    $report['manager_conflicts'][] = [
                        'row' => $item['row'],
                        'name' => $item['name'],
                        'type' => 'monitoring_director',
                    ];
                    $item['review_notes'][] = 'تم إلغاء دور مدير الرقابة بسبب وجود مسؤول مسبق';
                } else {
                    $monitoringDirectorClaimed = true;
                }
            }
        }

        unset($item);

        usort($parsed, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        return $parsed;
    }

    private function resolveCenterName(string $legacySection): string
    {
        if ($legacySection === $this->config['health_section_marker']) {
            return $this->config['centers']['health'];
        }

        return $this->config['centers']['association'];
    }

    private function resolveDepartment(
        string $departmentInput,
        string $legacySection,
        string $workplace,
        ?int $centerId
    ): ?Department {
        if ($departmentInput !== '') {
            $department = $this->findDepartmentByName($departmentInput, $centerId)
                ?? $this->findDepartmentByName($departmentInput);

            if ($department) {
                return $department;
            }
        }

        if ($legacySection === $this->config['health_section_marker']) {
            $departmentName = $this->matchMappedValue($workplace, $this->config['health_workplace_to_department']);

            if ($departmentName) {
                return $this->findDepartmentByName($departmentName, $this->centerId('health'));
            }
        }

        $mappedDepartment = $this->config['legacy_section_to_department'][$legacySection] ?? null;

        if ($mappedDepartment) {
            return $this->findDepartmentByName($mappedDepartment, $centerId ?? $this->centerId('association'));
        }

        return null;
    }

    private function resolveSection(
        string $legacySection,
        string $jobNature,
        string $workplace,
        ?Department $department
    ): ?Section {
        if ($this->shouldForceNullSection($jobNature)) {
            return null;
        }

        if ($legacySection !== '' && $this->sectionsByName->has($legacySection)) {
            $section = $this->sectionsByName->get($legacySection);

            if (! $department || (int) $section->department_id === (int) $department->id) {
                return $section;
            }
        }

        $mappedSectionName = $this->config['legacy_section_to_section'][$legacySection] ?? null;

        if ($mappedSectionName) {
            return $this->findSectionByName($mappedSectionName, $department?->id);
        }

        if ($legacySection === 'فروع') {
            $branchSectionName = $this->matchMappedValue($workplace, $this->config['branch_workplace_to_section']);

            if ($branchSectionName) {
                return $this->findSectionByName($branchSectionName, $department?->id);
            }
        }

        if ($legacySection === $this->config['health_section_marker']) {
            $healthSectionName = $this->matchMappedValue($jobNature, $this->config['health_nature_to_section']);

            if ($healthSectionName) {
                return $this->findSectionByName($healthSectionName, $department?->id);
            }
        }

        return null;
    }

    private function resolveRole(
        string $roleLabel,
        string $jobNature,
        string $legacySection,
        ?string $departmentName
    ): ?string {
        if ($roleLabel !== '' && isset($this->config['excel_role_to_system_role'][$roleLabel])) {
            return $this->config['excel_role_to_system_role'][$roleLabel];
        }

        foreach ($this->config['coordinator_nature_patterns'] as $pattern) {
            if ($this->containsArabic($jobNature, $pattern)) {
                return 'coordinator';
            }
        }

        if ($this->containsArabic($jobNature, 'رقابة') && $legacySection === 'الرقابة') {
            return 'monitor';
        }

        if ($this->containsArabic($jobNature, 'مدير عام') && $legacySection === 'إدارة') {
            return 'general_management';
        }

        if ($this->containsArabic($jobNature, 'رئيس قسم الرقابة') || $this->containsArabic($jobNature, 'مدير الرقابة')) {
            return 'monitoring_director';
        }

        if ($this->isOrdinaryStaffNature($jobNature)) {
            return null;
        }

        return null;
    }

    private function managerPriorityScore(?string $role, string $roleLabel): int
    {
        if (! $role) {
            return 0;
        }

        $priorities = $this->config['manager_role_priority'][$role] ?? [];

        foreach ($priorities as $index => $label) {
            if ($roleLabel === $label) {
                return 100 - $index;
            }
        }

        return 1;
    }

    private function shouldForceNullSection(string $jobNature): bool
    {
        foreach ($this->config['health_nature_null_roles'] as $marker) {
            if ($this->containsArabic($jobNature, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isOrdinaryStaffNature(string $jobNature): bool
    {
        $ordinaryMarkers = array_merge(
            $this->config['health_nature_null_roles'],
            ['مدرس', 'مدرسة', 'محاسب', 'سائق', 'حارس', 'تمريض', 'ممرض', 'ممرضة', 'كاتب', 'خدمات', 'آذن']
        );

        foreach ($ordinaryMarkers as $marker) {
            if ($this->containsArabic($jobNature, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function containsArabic(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function matchMappedValue(string $value, array $map): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach ($map as $needle => $mapped) {
            if ($this->containsArabic($value, $needle)) {
                return $mapped;
            }
        }

        return null;
    }

    private function findDepartmentByName(string $name, ?int $centerId = null): ?Department
    {
        $query = $this->departments->where('name', $name);

        if ($centerId) {
            $match = $query->firstWhere('center_id', $centerId);

            if ($match) {
                return $match;
            }
        }

        return $query->first();
    }

    private function findSectionByName(string $name, ?int $departmentId = null): ?Section
    {
        if ($departmentId) {
            $match = $this->sections->first(fn (Section $section) => $section->name === $name && (int) $section->department_id === $departmentId);

            if ($match) {
                return $match;
            }
        }

        return $this->sectionsByName->get($name);
    }

    private function centerId(string $key): ?int
    {
        $name = $this->config['centers'][$key] ?? null;

        return $name ? $this->centersByName->get($name)?->id : null;
    }

    private function normalizeNationalId(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function loadOrganization(): void
    {
        $this->centersByName = Center::query()->get()->keyBy('name');
        $this->departments = Department::query()->get();
        $this->sections = Section::query()->get();
        $this->sectionsByName = $this->sections->keyBy('name');
    }

    /**
     * @return array<int, string>
     */
    public function abilitiesForRole(?string $role): array
    {
        return app(RoleAbilitiesService::class)->forRole($role);
    }
}
