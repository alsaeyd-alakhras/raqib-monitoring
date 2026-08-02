<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\MonitoringActivity;

trait GeneratesActivityReferenceCode
{
    protected function generateReferenceCode(string $sourceType): string
    {
        $prefix = match ($sourceType) {
            'project' => 'MP',
            'external' => 'MA',
            'meeting' => 'MM',
            default => 'MX',
        };

        $lastNumber = MonitoringActivity::where('reference_code', 'like', $prefix . '-%')
            ->selectRaw('MAX(CAST(SUBSTR(reference_code, ?) AS UNSIGNED)) as max_num', [strlen($prefix) + 2])
            ->value('max_num');

        $nextNumber = ((int) $lastNumber) + 1;

        return $prefix . '-' . $nextNumber;
    }
}
