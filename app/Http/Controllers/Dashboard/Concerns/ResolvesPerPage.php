<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use Illuminate\Http\Request;

trait ResolvesPerPage
{
    protected function resolvePerPage(Request $request, int $default = 15): int
    {
        $perPage = $request->integer('per_page', $default);

        return in_array($perPage, [15, 25, 50, 100], true) ? $perPage : $default;
    }
}
