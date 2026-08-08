<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait AppliesDataTableQueryFilters
{
    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowedFields
     */
    protected function applyDataTableColumnFilters(EloquentBuilder|QueryBuilder $query, array $filters, array $allowedFields): void
    {
        foreach ($filters as $field => $values) {
            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            if (! is_array($values) || isset($values['from']) || isset($values['to'])) {
                continue;
            }

            $allowed = array_values(array_filter(
                $values,
                fn ($value) => ! in_array($value, ['الكل', 'all', 'All', '—'], true)
            ));

            if ($allowed === []) {
                continue;
            }

            $query->whereIn($field, $allowed);
        }
    }
}
