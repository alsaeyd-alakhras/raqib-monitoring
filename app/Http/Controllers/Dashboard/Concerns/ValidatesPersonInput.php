<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\Person;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ValidatesPersonInput
{
    /** @return array<string, mixed> */
    protected function personValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(Person::ROLES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePersonInput(Request $request, ?Person $except = null, bool $isSectionManager = false, bool $isSectionManagerUpdate = false): array
    {
        $currentPerson = auth()->user()?->person;
        $rules = $this->personValidationRules();

        if ($isSectionManagerUpdate) {
            $rules = [
                'role' => ['required', 'string', Rule::in(['project_manager', 'coordinator'])],
                'job_title' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'alternate_phone' => ['nullable', 'string', 'max:50'],
            ];
        } elseif ($isSectionManager) {
            $rules['role'] = ['required', 'string', Rule::in(['project_manager', 'coordinator'])];
            $rules['section_id'] = ['required', 'exists:sections,id'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $except, $currentPerson, $isSectionManager, $isSectionManagerUpdate) {
            if ($isSectionManagerUpdate) {
                return;
            }

            $role = $request->input('role');
            $departmentId = $request->input('department_id');
            $sectionId = $request->input('section_id');

            if (empty($role)) {
                return;
            }

            if ($isSectionManager) {
                if ((int) $sectionId !== (int) $currentPerson->section_id) {
                    $validator->errors()->add('section_id', 'لا يمكنك إضافة أو تعديل أشخاص خارج قسمك.');
                }
            }

            if (in_array($role, Person::rolesRequiringDepartment(), true) && empty($departmentId)) {
                $validator->errors()->add(
                    'department_id',
                    'الدائرة إلزامية لدور «' . (Person::roleLabels()[$role] ?? $role) . '».'
                );
            }

            if (in_array($role, Person::rolesRequiringSection(), true) && empty($sectionId)) {
                $validator->errors()->add(
                    'section_id',
                    'القسم إلزامي لدور «' . (Person::roleLabels()[$role] ?? $role) . '».'
                );
            }

            if ($sectionId) {
                $section = Section::find($sectionId);

                if (! $section) {
                    $validator->errors()->add('section_id', 'القسم المحدد غير موجود.');
                } elseif ($departmentId && (int) $section->department_id !== (int) $departmentId) {
                    $validator->errors()->add('section_id', 'القسم المختار لا يتبع الدائرة المحددة.');
                }
            }

            if ($role === 'department_manager' && $departmentId) {
                $existingManager = Person::query()
                    ->with('department')
                    ->where('role', 'department_manager')
                    ->where('department_id', $departmentId)
                    ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                    ->first();

                if ($existingManager) {
                    $validator->errors()->add(
                        'department_id',
                        'الدائرة «' . ($existingManager->department?->name ?? '') . '» لديها مدير دائرة بالفعل: ' . $existingManager->name . '.'
                    );
                }
            }

            if ($role === 'section_manager' && $sectionId) {
                $existingManager = Person::query()
                    ->with('section')
                    ->where('role', 'section_manager')
                    ->where('section_id', $sectionId)
                    ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                    ->first();

                if ($existingManager) {
                    $validator->errors()->add(
                        'section_id',
                        'القسم «' . ($existingManager->section?->name ?? '') . '» لديه مدير قسم بالفعل: ' . $existingManager->name . '.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if ($isSectionManagerUpdate && $except) {
            return array_merge($except->only([
                'name',
                'department_id',
                'section_id',
                'user_id',
                'organization',
            ]), $validated);
        }

        if (! empty($validated['section_id'])) {
            $section = Section::findOrFail($validated['section_id']);
            $validated['department_id'] = $section->department_id;
        } elseif (($validated['role'] ?? null) === 'department_manager') {
            $validated['section_id'] = null;
        } elseif (! in_array($validated['role'] ?? null, Person::rolesRequiringSection(), true)) {
            $validated['section_id'] = null;
        }

        if (empty($validated['role'])) {
            $validated['role'] = null;
        }

        if ($isSectionManager) {
            $validated['section_id'] = $currentPerson->section_id;
            $validated['department_id'] = $currentPerson->department_id;
        }

        return $validated;
    }

    protected function isSectionManagerActor(): bool
    {
        $user = auth()->user();

        return $user?->person?->role === 'section_manager' && ! $user->super_admin;
    }
}
