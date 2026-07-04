# 11 — اتفاقيات الواجهة (UI)

## Layout

**لا** `@extends('layouts.dashboard')` — استخدم `<x-front-layout>` فقط (`FrontLayout` → `front-layout-horizantal.blade.php`). القائمة الأفقية الفعلية المستخدَمة: `resources/views/layouts/partials/asideH.blade.php` (وحدات raqib فقط). `aside.blade.php` بقايا starter kit — لا تُعدَّل عليه عند إضافة روابط جديدة.

## نمط CRUD

كل module: `index`, `create`, `edit`, `_form` تحت `resources/views/dashboard/{module}/`.

```blade
<x-front-layout>
    <form action="{{ route('dashboard.{module}.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.{module}._form')
    </form>
</x-front-layout>
```

(للتعديل: `@method('put')` + route update)

**مكوّنات الحقول:** `<x-form.input>`, `<x-form.select>`, `<x-form.textarea>` في `resources/views/components/form/`.

### علة `<x-form.select>` مع مفاتيح int

- لا slot بين الوسمين — الخيارات فقط عبر `:optionsId` أو `:options`.
- **`:options` بمفاتيح int** → القيمة تصبح النص العربي وليس ID! يجب استخدام `:optionsId="$collectionOfObjectsWithIdAndName"` بدلاً من ذلك.
- **boolean 0/1**: استخدم `<select>` HTML يدوي — مرجع: `monitoring-activities/_form.blade.php` (`field_problem`, `is_passage_complete`).
- مفاتيح نصية (`['draft' => 'مسودة']`) تعمل بشكل صحيح مع `:options`.

هذه العلة أثّرت فعلياً على `department_id`/`section_id` في أكثر من مرحلة تنفيذ (phase-1 و phase-2) وصُحِّحت في كل موضع — انتبه لها عند أي فورم جديد بمفاتيح رقمية.

## جداول index — نمطان

1. **Bootstrap pagination** (الافتراضي لمعظم وحدات raqib): `$items = Model::paginate(15)` — مرجع `CenterController`, `ProjectController::index`, `MonitoringActivityController::index`.
2. **Yajra DataTables AJAX** (للبيانات الكبيرة/legacy): `if ($request->ajax())` + `DataTables::of()` + `js/datatable.js` — مرجع حي: `aid_distributions/` + `AidDistributionController`. اختياري، وليس إلزامياً لكل CRUD.

## Dependent dropdowns (مركز → دائرة → قسم)

`public/js/org-cascade.js` — `initOrgCascade({ centerId, departmentId, sectionId, departmentsUrl, sectionsUrl, selectedCenterId, ... })`. Cascade API (JSON):
- `GET departments/by-center/{center}` → `DepartmentController@byCenter`
- `GET sections/by-department/{department}` → `SectionController@byDepartment`

**تبسيط مقصود في phase-1/phase-2:** القائمة صغيرة (10 دوائر فقط)، ففورم المشروع/النشاط يعرض كل الدوائر مع اسم المركز كلاحقة بدل تصفية JS حية؛ الـ endpoints أعلاه موجودة كاحتياط لأي تحسين واجهة لاحق.

## `x-delete-form`

مكوّن تأكيد حذف (طلب تأكيد قبل الحذف الفعلي) — مُستخدم حالياً في شاشات المستخدمين والعملات (`users`, `currencies`).

## لوحة رئيسية مخصّصة بالدور

`HomeController` يبني لوحة مختلفة لكل دور وظيفي (إحصائيات + قسم "يتطلب إجراءك") بدل صفحة رئيسية عامة موحّدة.

## `@can` في Blade

```blade
@can('view', 'App\Models\Project')
@can('fill_coordinator', 'App\Models\Project')
@can('checklist_admin.manage')
```

## أخطاء متكررة أخرى (تجنّبها)

- أي view جديد = `<x-front-layout>` فقط — لا `@extends` من أي نوع.
- ability مشتقة من اسم Policy وليس اسم الجدول: `MonitoringActivity` → `monitoringactivities` (بلا underscore) — انظر [04-permissions](04-permissions.md).
- روابط جديدة لوحدات raqib تُضاف في `asideH.blade.php` فقط، أبداً في `aside.blade.php`.
