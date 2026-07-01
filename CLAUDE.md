# raqib-monitoring — Laravel dashboard conventions

هذا المشروع مبني على Laravel starter kit موجود مسبقاً (نظام توزيع مساعدات) يُعاد استخدامه لبناء نظام raqib (M&E/KPI). عند إضافة أي controller/view جديد لأي كيان في `plans/phases/`، اتّبع الأنماط التالية الموجودة فعلاً في الكود بدل اختراع أنماط جديدة.

## أين الخطط (مصدر الحقيقة للتنفيذ)

- `plans/01-context.md` .. `plans/04-open-questions.md` — القرارات النهائية المعتمدة من العميل (لا تُعدَّل).
- `plans/05-plan-update-1.md` — تحديثات لاحقة على القرارات (مطبّقة بالفعل على ملفات `phases/`).
- `plans/TASKS.md` — فهرس المراحل (4 مراحل، المرحلة 4 مؤجَّلة).
- `plans/phases/phase-1-foundation/overview.md` — الأساس: centers/departments/sections/people/funders + ثوابت + صلاحيات.
- `plans/phases/phase-2-monitoring-activities/{overview.md,schema-details.md}` — جدول `monitoring_activities` المحوري.
- `plans/phases/phase-3-projects/{overview.md,workflow-states.md,checklist-schema.md}` — المشاريع، دورة الاعتماد/المراقبة، قائمة التحقق الديناميكية.
- كل ملف `overview.md` per phase ينتهي بـ "قائمة المهام" (جدول بحالة ⬜/✅) — هذا هو مصدر التقدّم الفعلي للتنفيذ، حدّثه عند إنجاز كل بند.

## نمط مرجعي عملي: `resources/views/dashboard/aid_distributions/` + `AidDistributionController`

هذا المجلد هو **المثال الحي الأنسب** لفهم كيف تُبنى شاشة CRUD كاملة بأسلوب هذا المشروع (فورم مقسَّم بأقسام، جدول AJAX، صلاحيات، تصدير). **ليس كل جزء منه مطلوباً حرفياً في كل شاشة جديدة** — خذ منه ما يناسب حجم/طبيعة البيانات لكل كيان (جدول ثابت صغير مثل `funders` لا يحتاج كل تعقيد الفلاتر والتصدير، بينما `projects` أو `monitoring_activities` يستفيد منه بالكامل). التفاصيل الكاملة أدناه.

## Views: نمط `_form.blade.php` (مشترك بين create وedit)

- كل مجلد Resource تحت `resources/views/dashboard/{module}/` يحتوي: `index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`.
- `_form.blade.php` يحتوي **كل حقول الفورم فقط** (بدون `<form>` tag نفسه ولا CSRF).
- `create.blade.php` و`edit.blade.php` كلاهما مجرد wrapper رفيع:
  ```blade
  <x-front-layout>
      <form action="{{ route('dashboard.{module}.store') }}" method="post" class="col-12">
          @csrf
          @include('dashboard.{module}._form')
      </form>
  </x-front-layout>
  ```
  (edit يضيف `@method('put')` ويغيّر الـ route لـ update).
- استخدم مكوّنات Blade الجاهزة للحقول بدل HTML خام: `<x-form.input>`, `<x-form.select>`, `<x-form.textarea>` (انظر `resources/views/components/form/`). تدعم `old()` وعرض أخطاء التحقق تلقائياً.
- مثال مرجعي كامل: `resources/views/dashboard/aid_distributions/_form.blade.php` + `create.blade.php` + `edit.blade.php`.

## Views: نمط `index.blade.php` — جدول Server-Side (Yajra DataTables)

للجداول التي يُتوقع أن تكبر (مشاريع، أنشطة رقابية...)، لا تُستخدم صفحات Bootstrap بسيطة (`{{ $items->links() }}`) — بل نمط DataTables كامل عبر AJAX:

- الحزمة: `yajra/laravel-datatables-oracle` (مثبّتة في composer.json).
- **Controller**: دالة `index()` تتحقق `if ($request->ajax())` — إن كان AJAX، تبني الـ query (فلاتر، بحث، ترتيب مخصص عبر `applySort()`)، تُحوّل النتائج لمصفوفة بسيطة، ثم:
  ```php
  return DataTables::of($rows)->addIndexColumn()->addColumn('edit', fn($row) => $row['id'])->addColumn('delete', fn($row) => $row['id'])->make(true);
  ```
  إن لم يكن AJAX، تُرجع الـ view العادية بدون بيانات (البيانات تُحمَّل لاحقاً عبر JS).
- **View**: يحتوي `<table id="{module}-table">` بعناوين الأعمدة فقط (بدون `<tbody>` بيانات — يُملأ عبر JS)، ثم في `@push('scripts')`:
  - يحمّل ملفات `js/plugins/datatable/*.js` (jQuery DataTables + Buttons + export).
  - يعرّف متغيرات JS: `tableId`, `urlIndex`, `columnsTable` (تعريف كل عمود بما فيها `render()` مخصص للأزرار)، `fields`, روابط create/store/edit/update/delete (`route(...)`).
  - يستدعي `js/datatable.js` (الملف العام المشترك — 1032 سطر، لا تُعدّله لكل موديول، بل مرّر له الإعدادات عبر المتغيرات أعلاه) الذي يبني فعلياً الـ DataTable ويتعامل مع الفرز/الفلترة/الحذف/التصدير.
  - الصلاحيات تُمرَّر كمتغيرات JS منفصلة: `const abilityCreate = "{{ Auth::user()->can('create', 'App\\Models\\X') }}";` وتُستخدم داخل `render()` لإخفاء الأزرار.
- مثال مرجعي كامل: `resources/views/dashboard/aid_distributions/index.blade.php` + `AidDistributionController::index()`.
- **ملاحظة:** الجداول الصغيرة/الثابتة (كثوابت، سجل مستخدمين قليل) يمكن أن تبقى بنمط Bootstrap بسيط + pagination (انظر `resources/views/dashboard/users/index.blade.php`) — القرار حسب الحجم المتوقع للبيانات، وليس قاعدة صارمة.

## أخطاء متكررة وقعت فعلاً أثناء التنفيذ (تجنّبها)

- **لا يوجد `@extends('layouts.dashboard')` في هذا المشروع.** الـ layout الوحيد هو مكوّن Blade class-based: `<x-front-layout>` (يُترجَم لـ `app/View/Components/FrontLayout.php` → `resources/views/layouts/front-layout-horizantal.blade.php`، ويستخدم `{{ $slot }}`). أي view جديد يجب أن يبدأ بـ `<x-front-layout>` وينتهي بـ `</x-front-layout>` — وليس `@extends`/`@section('content')`.
- **مكوّن `<x-form.select>` (`resources/views/components/form/select.blade.php`) لا يدعم محتوى بين الوسمين (slot).** لا يوجد `{{ $slot }}` في تعريفه — أي `<option>` تضعها كـ children بين `<x-form.select>...</x-form.select>` تُتجاهَل تماماً بصمت (لا خطأ ظاهر، فقط قائمة فارغة). يجب تمرير الخيارات عبر prop: `:optionsId="$collection"` (لعناصر Eloquent فيها `id`/`name`، مثل `Center`/`User`) أو `:options="[key => label, ...]"` (لمصفوفة ثابتة). راجع `resources/views/components/form/select.blade.php` قبل استخدامه.
- **خطأ أخطر وأكثر خفاءً في نفس المكوّن: prop `options` مع مفاتيح int (integer keys) لا يعمل كما هو متوقَّع.** منطق المكوّن الداخلي: `$optionValue = is_int($key) ? $item : $key;` — أي أنه إذا كان المفتاح int (مثل `[1 => 'نعم', 0 => 'لا']` أو مصفوفة/Collection بمفاتيح IDs مثل `[5 => 'الدائرة X', 7 => 'الدائرة Y']`)، فإن **قيمة الـ option تصبح النص نفسه (label) وليس المفتاح (id)** — لأن هذا الفرع مصمَّم أصلاً لدعم مصفوفة تسلسلية بسيطة (`['a','b','c']` بمفاتيح تلقائية 0,1,2 حيث القيمة والعرض متطابقان)، وليس Mapping مقصود من مفتاح int إلى نص. **النتيجة: أي `<select>` لحقل مثل `department_id`/`section_id` (ID رقمي) أو حقل boolean (`0`/`1`) مبني عبر `:options="$intKeyedArray"` سيُرسِل قيمة النص العربي بدل الـ ID/0/1 الفعلي عند الحفظ، فيفشل التحقق (`exists:...` أو `boolean`) بصمت أو يُخزَّن خطأ.**
  - **للحقول برقم/ID كمفتاح** (مثل قائمة دوائر معنونة بأسماء المراكز): لا تستخدم `:options` إطلاقاً — ابنِ Collection من كائنات (`(object)['id'=>..., 'name'=>...]` أو `$collection->map(fn($x) => (object)['id'=>$x->id,'name'=>...])`) ومرِّرها عبر `:optionsId="$theCollection"`.
  - **لحقول boolean بسيطة (0/1، نعم/لا):** لا تستخدم المكوّن أصلاً — اكتب `<select>` عادي (Bootstrap `form-select`) يدوياً مع `@selected()` و`@error()` صريحين (مثال مرجعي: `resources/views/dashboard/monitoring-activities/_form.blade.php` حقلَي `field_problem` و`is_passage_complete`).
  - **مفاتيح نصية (string keys) تعمل بشكل صحيح** (مثل `['project' => 'مشروع', 'external' => 'خارجي']` أو `['pending_monitor' => 'بانتظار...', ...]`) — القيمة تصبح المفتاح كما هو متوقَّع، لا مشكلة هناك.
  - هذا الخطأ وقع فعلياً في `sections/_form.blade.php` (مرحلة 1) وتم تصحيحه لاحقاً؛ راجع أي استخدام سابق لـ `:options` بمفاتيح int قبل الاعتماد عليه.

## Policies و Abilities — نمط حاسم لتحديد الـ ability string

- كل Policy فارغة تماماً وتَرِث من `App\Policies\ModelPolicy` (انظر `ConstantPolicy.php`). لا تكتب دوال `view/create/update/delete` صراحةً.
- `ModelPolicy::__call()` يشتق سلسلة الصلاحية تلقائياً من **اسم كلاس الـ Policy نفسه** (وليس الموديل): `{Policy}Policy` → `Str::plural(Str::lower($class))` + `.` + الفعل. مثال: `ConstantPolicy` → `constants.view`.
- Laravel يحل الـ Policy بالتوافق الاسمي التلقائي `App\Policies\{Model}Policy` لـ `App\Models\{Model}` — **لا يوجد** أي `Gate::policy()` يدوي في `AppServiceProvider`.
- **تبعاً لذلك:** يجب أن يكون هناك Policy واحدة **لكل Model** (وليس Policy مُدمجة لعدة موديلات)، وأن يتطابق مفتاح `data/abilities.php` مع الجمع اللاتيني لاسم الموديل (مثل `constants`, `currencies`, `users`, `activitylogs`).
- **قرار اتُّخذ فعلياً أثناء التنفيذ (مرحلة 1):** خطة `phase-1-foundation/overview.md` اقترحت "Policy واحدة (`OrganizationalPolicy`) لـ centers/departments/sections" — هذا **لا يتوافق** مع القيد أعلاه (لا يوجد موديل اسمه Organizational). التنفيذ الفعلي استخدم بدلاً منها: `CenterPolicy`, `DepartmentPolicy`, `SectionPolicy` منفصلة، وبالتالي `data/abilities.php` يحتاج 3 مجموعات منفصلة (`centers`, `departments`, `sections`) بدل مجموعة `organizational` واحدة. طبّق هذا القرار في أي مكان آخر بالخطط يفترض Policy/ability مُدمجة لعدة جداول.
