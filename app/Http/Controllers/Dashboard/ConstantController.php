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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('update', Constant::class);

        $registry = $this->registry();
        $allowedStructuredKeys = array_keys($registry);

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
                'keys' => ['project_types', 'association_offices'],
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
        ];
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
