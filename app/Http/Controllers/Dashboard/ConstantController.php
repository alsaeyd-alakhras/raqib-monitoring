<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Constant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConstantController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', Constant::class);

        $registry = $this->registry();
        $stored = Constant::query()->get()->keyBy('key');
        $decodedValues = [];

        foreach (array_keys($registry) as $key) {
            $decodedValues[$key] = $this->decodeValue($stored->get($key)?->value);
        }

        return view('dashboard.pages.constants', [
            'tabGroups' => $this->tabGroups(),
            'registry' => $registry,
            'decodedValues' => $decodedValues,
            'legacyFields' => $this->legacyNumericFields(),
            'legacyValues' => $this->legacyValues($stored),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('update', Constant::class);

        $registry = $this->registry();
        $allowedStructuredKeys = array_keys($registry);
        $allowedLegacyKeys = array_keys($this->legacyNumericFields());

        foreach ($request->input('constants', []) as $key => $values) {
            if (! in_array($key, $allowedStructuredKeys, true)) {
                continue;
            }

            $cleaned = $this->cleanConstantValue($key, $values, $registry[$key]['value_shape']);

            Constant::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($cleaned, JSON_UNESCAPED_UNICODE)]
            );
        }

        foreach ($allowedLegacyKeys as $key) {
            if (! $request->has($key)) {
                continue;
            }

            Constant::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $request->input($key)]
            );
        }

        return redirect()
            ->route('dashboard.constants.index')
            ->with('success', 'تم تحديث الثوابت بنجاح.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorize('update', Constant::class);

        if ($request->state_effectiveness) {
            Constant::findOrFail($request->state_effectiveness)->delete();
        }

        return redirect()->route('dashboard.constants.index')->with('danger', 'تم حذف القيمة المحددة');
    }

    /** @return array<string, array<string, mixed>> */
    private function registry(): array
    {
        return require base_path('data/constants-registry.php');
    }

    /** @return array<string, array<string, mixed>> */
    private function tabGroups(): array
    {
        return [
            'projects' => [
                'label' => 'قوائم المشاريع',
                'icon' => 'fa-diagram-project',
                'keys' => ['project_types'],
            ],
            'monitoring' => [
                'label' => 'قوائم الرقابة',
                'icon' => 'fa-clipboard-check',
                'keys' => ['activity_types', 'monitoring_methods', 'monitoring_stages'],
            ],
            'scales' => [
                'label' => 'مقاييس التقييم',
                'icon' => 'fa-chart-line',
                'keys' => ['scale_execution', 'scale_quality', 'scale_closure', 'scale_deduction', 'scale_kpi'],
            ],
            'legacy' => [
                'label' => 'ثوابت إدارية',
                'icon' => 'fa-coins',
                'type' => 'legacy',
            ],
        ];
    }

    /** @return array<string, array<string, string>> */
    private function legacyNumericFields(): array
    {
        return [
            'advance_payment_permanent' => [
                'label' => 'مبلغ السلفة — مداوم',
                'suffix' => '₪',
                'group' => 'advance',
            ],
            'advance_payment_non_permanent' => [
                'label' => 'مبلغ السلفة — غير مداوم',
                'suffix' => '₪',
                'group' => 'advance',
            ],
            'advance_payment_rate' => [
                'label' => 'مبلغ السلفة — نسبة',
                'suffix' => '₪',
                'group' => 'advance',
            ],
            'advance_payment_riyadh' => [
                'label' => 'مبلغ السلفة — رياض',
                'suffix' => '₪',
                'group' => 'advance',
            ],
            'termination_service' => [
                'label' => 'نسبة نهاية الخدمة للمؤسسة',
                'suffix' => '%',
                'group' => 'termination',
            ],
            'termination_employee' => [
                'label' => 'نسبة إدخار للموظف',
                'suffix' => '%',
                'group' => 'termination',
            ],
            'health_bachelor' => [
                'label' => 'رواتب الصحة — بكالوريوس',
                'suffix' => '₪',
                'group' => 'health',
            ],
            'health_diploma' => [
                'label' => 'رواتب الصحة — دبلوم',
                'suffix' => '₪',
                'group' => 'health',
            ],
            'health_secondary' => [
                'label' => 'رواتب الصحة — ثانوية عامة',
                'suffix' => '₪',
                'group' => 'health',
            ],
        ];
    }

    /** @param \Illuminate\Support\Collection<string, Constant> $stored */
    private function legacyValues($stored): array
    {
        $values = [];

        foreach (array_keys($this->legacyNumericFields()) as $key) {
            $values[$key] = $stored->get($key)?->value ?? '0';
        }

        return $values;
    }

    private function decodeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /** @return array<int, mixed> */
    private function cleanConstantValue(string $key, mixed $values, string $shape): array
    {
        if (str_starts_with($shape, 'list<string>')) {
            return array_values(array_filter(
                array_map(static fn ($item) => trim((string) $item), (array) $values),
                static fn ($item) => $item !== ''
            ));
        }

        if (str_contains($shape, '{min:int,label:string}')) {
            $rows = [];

            foreach ((array) $values as $row) {
                $label = trim((string) ($row['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $rows[] = [
                    'min' => (int) ($row['min'] ?? 0),
                    'label' => $label,
                ];
            }

            usort($rows, static fn ($a, $b) => $b['min'] <=> $a['min']);

            return $rows;
        }

        if (str_contains($shape, '{value:int,label:string}')) {
            $rows = [];

            foreach ((array) $values as $row) {
                $label = trim((string) ($row['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $rows[] = [
                    'value' => (int) ($row['value'] ?? 0),
                    'label' => $label,
                ];
            }

            return $rows;
        }

        return [];
    }
}
