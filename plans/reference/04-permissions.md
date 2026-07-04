# 04 — الصلاحيات (Abilities & Policies)

## النمط العام: ModelPolicy مشتقة تلقائياً

كل Policy فارغة ترث `App\Policies\ModelPolicy` — لا تكتب `view/create/update/delete` صراحةً. `ModelPolicy::__call()` يشتق اسم الـ ability من اسم كلاس الـ Policy: `{Model}Policy` → `{plural-lowercase-model}.{kebab-action}`. مثال: `MonitoringActivityPolicy` → `monitoringactivities.view` (بلا underscore — يطابق `plural(lowercase(class))` حرفياً).

Laravel يحل الـ Policy تلقائياً عبر الاتفاقية `App\Models\{Model}` → `App\Policies\{Model}Policy`. **لا** `Gate::policy()` يدوي إلا للاستثناءات التالية (لأنها ليست Model حقيقياً):

```php
// AppServiceProvider
Gate::define('checklist_admin.manage', ...);  // إدارة بنية قائمة التحقق — لا model باسم ChecklistAdmin
Gate::define('reports.view', ...);             // بقايا starter kit
Gate::define('admins.super', ...);
```

## تخزين الصلاحيات: `role_user`

جدول pivot فعلي اسمه `role_user` (رغم أن migration الملف مسمّى `create_role_users_table`؛ Model `RoleUser` يحدد `protected $table = 'role_user'` صراحةً). كل صف = `{user_id, role_name}` حيث `role_name` سلسلة ability (مثل `projects.view`, `projects.approve_department`). **ليس نظام roles تقليدياً** — أقرب لـ abilities array مُخزَّنة بقاعدة بيانات بدل ملف PHP.

`ModelPolicy::EMPLOYEE_ALLOWED_ABILITIES` (ثابت قديم يحدد صلاحيات افتراضية لـ `user_type='employee'`) **أُزيل نهائياً** — كل الصلاحيات الفعلية اليوم تُشتق من صفوف `role_user` فقط (تصحيح 2026-07-04، انظر `QA-REPORT.md`).

## سجل الصلاحيات: `data/abilities.php`

مجموعات الصلاحيات لشاشة إدارة المستخدمين. **المفتاح = plural lowercase لاسم الموديل** بلا underscore (`monitoringactivities` وليس `monitoring_activities`) — يجب أن يطابق تماماً اشتقاق `ModelPolicy` وإلا فشل الربط بين الشاشة وفحص الصلاحية الفعلي.

عند إضافة ability جديدة: أضفها في `data/abilities.php` + استخدمها في Controller/View (`$this->authorize(...)` أو `@can`) + امنحها للمستخدمين عبر واجهة إدارة المستخدمين (تُنشئ صفوف `role_user`).

### أمثلة ability مركّبة (غير CRUD قياسي) في `projects`

`approve_department` (مدير الدائرة فقط)، `fill_coordinator` (المنسق أو مدير المشروع نيابةً)، `fill_monitor` (المراقب)، `reject` (ability مستقلة — الحامل الافتراضي مدير الرقابة العامة، لكن **قابلة للمنح لأي دور آخر دون تعديل بنيوي** — هذا التصميم مقصود منذ البداية).

### أمثلة في `monitoringactivities`

`assign_monitor`, `set_monitoring_info`, `confirm_completion` (مدير الرقابة العامة فقط)، `edit_ratings` (مدير الرقابة + الإدارة العامة).

## قرار تنفيذي مثبَّت: لا `OrganizationalPolicy` موحّدة

خطة التخطيط الأولى (phase-1) اقترحت Policy موحّدة باسم `OrganizationalPolicy` للهرم التنظيمي بأكمله. **لم تُطبَّق.** التنفيذ الفعلي: `CenterPolicy`, `DepartmentPolicy`, `SectionPolicy` منفصلة تماماً، كل واحدة بمجموعة abilities خاصة بها في `data/abilities.php` (`centers`, `departments`, `sections`). هذا التصحيح موثَّق أيضاً في `CLAUDE.md` مباشرة — لا تُعد اقتراح التوحيد عند العمل على هذا الجزء.

## Authorization في Controller

`$this->authorize('view', Project::class)` — يمرَّر **class** وليس instance (لأن `ModelPolicy` لا يستخدم instance في اشتقاقه). في Blade: `@can('view', 'App\Models\Project')` أو `@can('checklist_admin.manage')` للـ Gate اليدوي.

## DemoUsersSeeder

`php artisan db:seed --class=DemoUsersSeeder` ينشئ ~18 مستخدماً وهمياً بأدوار وصلاحيات واقعية (كلمة المرور: `password`) لاختبار كل مسارات الصلاحيات يدوياً. **لا يمس `super_admin`.** حسابات مرجعية استُخدمت في جولة QA الأخيرة: `pm_ahmad` (مدير مشروع)، `coord_layla` (منسق)، `dm_projects` (مدير دائرة)، `mon_dir` (مدير رقابة)، `monitor1` (مراقب).
