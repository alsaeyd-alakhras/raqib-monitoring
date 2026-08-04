<?php

namespace App\Http\Controllers\Dashboard\Concerns;

trait ConvertsMultilineNotes
{
    /** @return list<string> */
    protected function linesToArray(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $text)))));
    }
}
