# raqib-monitoring — دليل المشروع والاتفاقيات

## ما هو هذا المشروع؟

**raqib-monitoring** هو نظام Laravel لـ **الرقابة والمتابعة (M&E/KPI)** لمؤسسة خيرية. يُدار من خلاله:

- **البيانات الأساسية**: الهيكل التنظيمي (مراكز → دوائر → أقسام)، الأشخاص بأدوارهم، الممولون، ثوابت النظام.
- **النشاطات الرقابية** (`monitoring_activities`): سجل مركزي لكل نشاط رقابي (من مشروع أو خارجي أو اجتماع).
- **المشاريع** (`projects`): دورة اعتماد كاملة من المسودة حتى «تم المرور»، مع **قائمة تحقق ديناميكية** (منسق + مراقب) وربط بنشاط رقابي أساسي.

المشروع مبني فوق **Laravel starter kit** كان أصلاً لنظام **توزيع مساعدات** (`aid_distributions`، تقارير، brokers/items...). كثير من ذلك **باقٍ في الكود** (controllers، views، routes، aside قديم) لكن **القائمة الفعلية للمستخدم** في `asideH.blade.php` تعرض وحدات raqib فقط.

**اللغة والواجهة**: عربية RTL، Bootstrap 5، قالب Vuexy/horizontal layout.

**المكدس**: Laravel 11، PHP 8.2+، Fortify للمصادقة، Yajra DataTables (للجداول الكبيرة)، Maatwebsite Excel، mPDF.

---

## خريطة الوحدات المنفذة

| الوحدة | Model | Controller | Views | جدول القائمة |
|--------|-------|------------|-------|--------------|
| المراكز | `Center` | `CenterController` | `dashboard/centers/` | Bootstrap pagination |
| الدوائر | `Department` | `DepartmentController` | `dashboard/departments/` | Bootstrap pagination |
| الأقسام | `Section` | `SectionController` | `dashboard/sections/` | Bootstrap pagination |
| الأشخاص | `Person` | `PersonController` | `dashboard/people/` | Bootstrap pagination |
| الممولون | `Funder` | `FunderController` | `dashboard/funders/` | Bootstrap pagination |
| ثوابت النظام | `Constant` | `ConstantController` | `dashboard/pages/constants` | صفحة واحدة |
| المستخدمون | `User` | `UserController` | `dashboard/users/` | Bootstrap pagination |
| النشاطات الرقابية | `MonitoringActivity` | `MonitoringActivityController` | `dashboard/monitoring-activities/` | Bootstrap + فلاتر |
| المشاريع | `Project` | `ProjectController` | `dashboard/projects/` | Bootstrap + workflow |
| إدارة قائمة التحقق | — | `ChecklistAdminController` | `dashboard/checklist-admin/` | بدون model مستقل |
| سجل العمليات | `ActivityLog` | `ActivityLogController` | `dashboard/pages/logs` | — |

**Models مساندة**: `ChecklistGroup`, `ChecklistItem`, `ProjectChecklistValue`, `RoleUser`, `Currency` (legacy).

**Routes**: كل مسارات اللوحة في `routes/dashboard.php` (يُحمَّل من `routes/web.php`). Prefix فارغ — الجذر `/` هو home.

---

## الهيكل التنظيمي والأدوار

```
Center (مركز رئيسي، مثل «الجمعية»)
  └── Department (دائرة)
        └── Section (قسم / مكتب)
```

**Person** = شخص ب دور وظيفي، قد يُربط بـ **User** (حساب دخول):

| `Person.role` | الوصف | ملاحظات |
|---------------|--------|---------|
| `project_manager` | مدير مشروع | يتطلب `department_id` |
| `coordinator` | منسق | يعبّئ عمود المنسق في قائمة التحقق |
| `department_manager` | مدير دائرة | واحد لكل دائرة؛ يوافق على المشاريع |
| `monitoring_director` | مدير الرقابة العامة | يعيّن مراقباً، يؤكد المرور |
| `monitor` | مراقب ميداني | يعبّئ عمود المراقب + KPI |
| `general_management` | الإدارة العامة | عرض فقط غالباً |
| `admin` | أدمن نظام | إدارة مستخدمين/ثوابت/قائمة تحقق — **ليس** `super_admin` |

**User**:
- `super_admin = 1` → يتجاوز كل الصلاحيات (`Gate::before` في `AppServiceProvider`).
- `user_type = 'employee'` → صلاحيات محدودة مسبقاً في `ModelPolicy::EMPLOYEE_ALLOWED_ABILITIES` (بقايا starter kit).
- الصلاحيات الفعلية لمعظم المستخدمين: صفوف في `role_users` حيث `role_name` = سلسلة ability (مثل `projects.view`).

**ربط User ↔ Person**: `users.id` ← `people.user_id`. منطق الرؤية في `Project::scopeVisibleToUser()` يعتمد على `auth()->user()->person->role`.

---

## دورة حياة المشروع (workflow)

حالات `projects.workflow_status`:

```
draft
  → pending_coordinator / coordinator_filling   (المنسق)
  → pending_dept_manager                          (مدير الدائرة)
  → pending_monitoring_manager                    (مدير الرقابة — تعيين مراقب)
  → monitoring_in_progress                        (المراقب يعمل)
  → pending_monitoring_confirmation               (مدير الرقابة — تأكيد)
  → passage_complete                              (تم المرور)
  أو rejected في أي مرحلة (مع gap_owner + rejection_reason)
```

**إجراءات POST** (في `ProjectController` + routes تحت `projects/{project}/`):

| Route name | الغرض |
|------------|--------|
| `submit-to-coordinator` | إرسال للمنسق |
| `fill-coordinator` | حفظ قائمة تحقق المنسق + `coordinator_readiness_pct` |
| `submit-to-dept-manager` | إرسال لمدير الدائرة |
| `approve-department` | موافقة مدير الدائرة |
| `set-monitoring-info` | تحديد طريقة/مرحلة المراقبة |
| `assign-monitor` | تعيين مراقب + إنشاء `MonitoringActivity` أساسي |
| `monitor-work` (GET) | شاشة عمل المراقب |
| `fill-monitor` | حفظ قائمة تحقق المراقب |
| `confirm-monitoring` | المراقب يرسل لمدير الرقابة |
| `confirm-passage` | مدير الرقابة يؤكد «تم المرور» |
| `reject` | رفض مع `gap_owner` |
| `reroute` | إعادة توجيه بعد الرفض |

**رقم المشروع**: صيغة `P-{n}` (مثل `P-1`). التحقق AJAX: `GET projects/check-project-number`. Helpers في `Project`: `generateProjectNumber()`, `isProjectNumberAvailable()`.

**المنسق**: ثلاثة أوضاع (`coordinator_mode` في الفورم):
- `person` → `coordinator_id`
- `self` → المنسق = مدير المشروع (`coordinator_id = project_manager_id`)
- `external` → `coordinator_external_name` (بدون person)

**الرؤية**: `Project::visibleToUser()` / `isVisibleToUser()` — مدير المشروع يرى مشاريعه، مدير الدائرة يرى مشاريع مديري مشاريع في دائرته، المنسق/المراقب يريان المعيّنين لهما فقط.

---

## النشاطات الرقابية

جدول **`monitoring_activities`** محوري — يربط:
- `source_type` + `source_id` (مشروع / خارجي / اجتماع)
- `activity_role`: `primary` (مرتبط بمشروع عبر `projects.primary_monitoring_activity_id`) أو `secondary`
- التسلسل الهرمي: `center_id`, `department_id`, `section_id`
- مقاييس KPI: `execution_value`, `quality_value`, `closure_value`, `deduction_value` → `kpi_value` + `kpi_rating` (محسوب تلقائياً في `booted()`)

حالات `workflow_status`: `pending_monitor` → `in_progress` → `pending_confirmation` → `completed` (+ `is_passage_complete`).

**مزامنة مع المشروع**: `Project::syncMonitoringWorkflowState()` يصلح التناقض بين حالة المشروع والنشاط.

---

## قائمة التحقق (Checklist)

- **إدارة البنية**: `checklist_groups` + `checklist_items` عبر `ChecklistAdminController` — صلاحية **`checklist_admin.manage`** (Gate يدوي في `AppServiceProvider`، **بدون** ModelPolicy لأنه لا model اسمه ChecklistAdmin).
- **قيم المشروع**: `project_checklist_values` — عمودان: `coordinator_value`, `monitor_value` (قيم: `ready`, `partial`, `not_ready`, `not_required` من ثابت `checklist_options`).
- **حساب الجاهزية**: `Project::recalculateReadiness()` — متوسط مجموعات: `(ready + 0.5×partial) / (total - not_required)` لكل مجموعة، ثم متوسط المجموعات. النتيجة تُنسخ إلى `monitoring_activities.execution_value` للنشاط الأساسي.
- **Partials في views**: `_checklist_edit`, `_checklist_display`, `_checklist_styles`, `_coordinator_checklist`, `_project_summary`, `_reject_modal`.

---

## ثوابت النظام (Constants)

- **سجل المفاتيح**: `data/constants-registry.php` — توثيق كل مفتاح واستخدامه.
- **القيم**: جدول `constants` (key → JSON value). Seeder: `ConstantsSeeder`.
- **قراءة في الكود**: `Constant::where('key', $key)->value('value')` ثم `json_decode` — انظر helpers في `ProjectController` / `MonitoringActivityController`.
- **مفاتيح مهمة**: `project_types`, `monitoring_methods`, `monitoring_stages`, `activity_types`, `source_types`, `checklist_options`, `scale_execution/quality/closure/deduction`, `scale_kpi`.

---

## الصلاحيات (Policies & Abilities)

### النمط العام (ModelPolicy)

- كل Policy فارغة تَرِث `App\Policies\ModelPolicy` — لا تكتب `view/create/update/delete` صراحةً.
- `ModelPolicy::__call()` يشتق ability من **اسم Policy**: `{Policy}Policy` → plural lowercase + `.` + kebab action. مثال: `ProjectPolicy` → `projects.view`, `projects.approve_department`.
- Laravel يحل Policy تلقائياً: `App\Models\{Model}` → `App\Policies\{Model}Policy`.
- **لا** `Gate::policy()` يدوي — **استثناء**: `checklist_admin.manage`, `reports.view`, `admins.super`.

### ملف abilities

- `data/abilities.php` — مجموعات الصلاحيات لشاشة إدارة المستخدمين. المفتاح = plural lowercase لاسم الموديل (`monitoringactivities` وليس `monitoring_activities`).
- عند إضافة ability جديدة: أضفها في `data/abilities.php` + استخدمها في Controller/View + أ assign للمستخدمين عبر `RoleUser`.

### قرار تنفيذي (مرحلة 1)

خطة phase-1 اقترحت `OrganizationalPolicy` موحّدة — **لا تُطبَّق**. التنفيذ الفعلي: `CenterPolicy`, `DepartmentPolicy`, `SectionPolicy` منفصلة + 3 مجموعات في abilities.

---

## Routing & Middleware

- **Dashboard middleware**: `check.cookie` (`CheckUserCookie`) — يتحقق من session أو cookie `user_id` ويسجّل الدخول تلقائياً.
- **Activity log**: `LogLastUserActivity` على كل web requests.
- **Cascade API** (JSON للقوائم المتتابعة):
  - `GET departments/by-center/{center}` → `DepartmentController@byCenter`
  - `GET sections/by-department/{department}` → `SectionController@byDepartment`
- **JS**: `public/js/org-cascade.js` — `initOrgCascade({ centerId, departmentId, sectionId, departmentsUrl, sectionsUrl, selectedCenterId, ... })`

---

## Views — الاتفاقيات

### Layout

- **لا** `@extends('layouts.dashboard')`. استخدم `<x-front-layout>` فقط (`FrontLayout` → `front-layout-horizantal.blade.php`).
- **القائمة الأفقية الفعلية**: `resources/views/layouts/partials/asideH.blade.php` (raqib). `aside.blade.php` = بقايا starter kit.

### CRUD pattern

كل module: `index`, `create`, `edit`, `_form` تحت `resources/views/dashboard/{module}/`.

```blade
<x-front-layout>
    <form action="{{ route('dashboard.{module}.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.{module}._form')
    </form>
</x-front-layout>
```

(edit: `@method('put')` + route update)

**مكوّنات الحقول**: `<x-form.input>`, `<x-form.select>`, `<x-form.textarea>` في `resources/views/components/form/`.

### جداول index — نمطان

1. **Bootstrap pagination** (الافتراضي لمعظم وحدات raqib): Controller يمرّر `$items = Model::paginate(15)` — مرجع: `CenterController`, `ProjectController::index`, `MonitoringActivityController::index`.

2. **Yajra DataTables AJAX** (للبيانات الكبيرة / legacy): `if ($request->ajax())` + `DataTables::of()` + `js/datatable.js`. **مرجع حي**: `aid_distributions/` + `AidDistributionController`. استخدمه عند الحاجة للفلاتر المتقدمة والتصدير — ليس إلزامياً لكل CRUD.

### `@can` في Blade

```blade
@can('view', 'App\Models\Project')
@can('fill_coordinator', 'App\Models\Project')
@can('checklist_admin.manage')
```

---

## Controllers — أنماط مرجعية

| النمط | متى | مرجع |
|-------|-----|------|
| CRUD بسيط + pagination | كيانات ثابتة صغيرة | `CenterController` |
| CRUD + فلاتر query string | نشاطات رقابية | `MonitoringActivityController` |
| CRUD + workflow actions | مشاريع | `ProjectController` (~1100 سطر) |
| DataTables AJAX | توزيع مساعدات (legacy) | `AidDistributionController` |
| Gate مباشر بدون model | checklist admin | `ChecklistAdminController` |

**Authorization في Controller**: `$this->authorize('view', Project::class)` — يمرّر **class** وليس instance (ModelPolicy لا يستخدم instance).

---

## أخطاء متكررة (تجنّبها)

### `<x-form.select>` — مفاتيح int

- **لا slot** بين الوسمين — الخيارات عبر `:optionsId` أو `:options` فقط.
- **`:options` بمفاتيح int** → القيمة تصبح النص العربي وليس ID! استخدم `:optionsId="$collectionOfObjectsWithIdAndName"`.
- **boolean 0/1**: `<select>` HTML يدوي — مرجع: `monitoring-activities/_form.blade.php` (`field_problem`, `is_passage_complete`).
- **string keys** في `:options` تعمل (مثل `['draft' => 'مسودة']`).

### `@extends` / layout خاطئ

أي view جديد = `<x-front-layout>` فقط.

### Policy/ability mismatch

اسم ability = plural lowercase من Policy class. `MonitoringActivity` → `monitoringactivities` (بدون underscore).

### تعديل aside القديم

أضف روابط raqib في **`asideH.blade.php`** وليس `aside.blade.php`.

---

## الاختبار والبيانات التجريبية

```bash
php artisan migrate --seed          # super_admin + constants + org + checklist
php artisan db:seed --class=DemoUsersSeeder   # 18 مستخدم وهمي، password: password
php artisan test --filter=ProjectsSmokeTest     # workflow كامل
npm run test:e2e                                # Playwright (tests/e2e/)
```

**DemoUsersSeeder**: يُنشئ مستخدمين بأدوار وصلاحيات واقعية — **لا يمس** `super_admin`.

**ProjectsSmokeTest**: يغطي إنشاء مشروع → منسق → مدير دائرة → تعيين مراقب → تعبئة → تأكيد مرور + إدارة checklist.

---

## Legacy من starter kit (لا تخلط مع raqib)

ما زال موجوداً في الكود لكن **خارج قائمة raqib**:
- `AidDistributionController`, `ReportController`, views تحت `reports/`, `aid_distributions/`
- Models/policies: `AidDistribution`, brokers, items, allocations... (إن وُجدت)
- Routes: `reports/*`, `aid-distributions-filters/*`
- `aside.blade.php` — قائمة قديمة

**عند التعديل**: لا تحذف legacy إلا إذا طُلب صراحة. ركّز على patterns وحدات raqib أعلاه.

---

## إضافة ميزة جديدة — checklist سريع

1. Migration + Model + Policy فارغة + entry في `data/abilities.php`.
2. Controller في `app/Http/Controllers/Dashboard/` + routes في `routes/dashboard.php`.
3. Views تحت `resources/views/dashboard/{module}/` بنمط `_form`.
4. رابط في `asideH.blade.php` مع `@can('view', 'App\Models\X')`.
5. إن وُجدت قيم enum → ثابت في `constants-registry.php` + `ConstantsSeeder`.
6. Feature test إن كان workflow معقّداً.

---

## ملاحظة عن `plans/`

الوثائق التفصيلية الأصلية (context، phases، workflow-states، checklist-schema) كانت في مجلد `plans/` أثناء التخطيط. **قد لا يكون المجلد موجوداً في clone** — مصدر الحقيقة للتنفيذ هو **الكود الحالي** + هذا الملف. عند التعارض: **الكode يفوز**.
