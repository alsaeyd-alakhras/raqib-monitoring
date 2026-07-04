# المرحلة 3 — المشاريع (Projects)

> **سجل تنفيذ تاريخي.** المرجع الحي الحالي (as-built) هو `plans/reference/` — خصوصاً [06-project-approval-workflow.md](../../reference/06-project-approval-workflow.md)، [07-monitoring-workflow.md](../../reference/07-monitoring-workflow.md)، [08-reject-and-return.md](../../reference/08-reject-and-return.md) (الرفض تطوَّر لاحقاً إلى `return_target` موحَّد — أحدث من هذا الملف) و[09-checklist-and-readiness.md](../../reference/09-checklist-and-readiness.md). عند التعارض: الكود يفوز أولاً، ثم `reference/`.

## الهدف
بناء كيان المشروع الكامل: الجدول الغني بالتفاصيل، الفورم الموحّد المقسَّم بأقسام، دورة الاعتماد الإدارية (5 خطوات)، دورة المراقبة الموحّدة (مع جدول النشاطات)، وقائمة التحقق الديناميكية بنسب الجاهزية.

**يعتمد على:** المرحلتين 1 و 2 (people، organizational، monitoring_activities)

---

## الجداول الجديدة

### 1. `projects` — المشاريع
> بنية الحقول الكاملة في [checklist-schema.md](checklist-schema.md) (قسم المشروع) وفي `02-data-model.md`

**حالة سير العمل (workflow_status):** انظر [workflow-states.md](workflow-states.md)

**[محدَّث] علاقة 1:N مع monitoring_activities:** عمود `primary_monitoring_activity_id` (FK nullable، بدلاً من `monitoring_activity_id`) يُملأ لحظة توليد **النشاط الأساسي فقط** — أي عند تعيين مدير الرقابة العامة مراقباً محدداً للمشروع (وليس عند مجرد وصول/استلام المشروع). المشروع قد يولّد لاحقاً أنشطة تابعة إضافية (`activity_role='secondary'`) لا يُشار لها من هذا العمود؛ تُستعلَم عبر `monitoring_activities.source_type='project' AND source_id=projects.id`.

---

### 2. `checklist_groups` — مجموعات قائمة التحقق (ديناميكية)
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `name` | string | اسم المجموعة (مثل: "اللوجستيك والموارد") |
| `order` | integer | ترتيب العرض |
| `is_active` | boolean | default: true |
| `timestamps` | | |

البيانات الأولية (seed): "اللوجستيك والموارد"، "التحضيرات الميدانية"، "الموارد البشرية"

---

### 3. `checklist_items` — بنود قائمة التحقق (ديناميكية)
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `group_id` | FK → checklist_groups | cascadeOnDelete |
| `name` | string | نص البند |
| `has_person_field` | boolean | default: false — true لبنود "الموارد البشرية" التي تحمل اسم شخص |
| `order` | integer | ترتيب داخل المجموعة |
| `is_active` | boolean | default: true |
| `timestamps` | | |

---

### 4. `project_checklist_values` — قيم قائمة التحقق لكل مشروع
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `project_id` | FK → projects | cascadeOnDelete |
| `checklist_item_id` | FK → checklist_items | |
| `coordinator_value` | enum('ready','partial','not_ready','not_required') nullable | قيمة المنسق |
| `monitor_value` | enum('ready','partial','not_ready','not_required') nullable | قيمة المراقب |
| `person_name` | string nullable | اسم الشخص (لبنود has_person_field=true) |
| `timestamps` | | |

**unique constraint:** `(project_id, checklist_item_id)`

---

## بنية فورم المشروع (أقسام، وليس Wizard)

الفورم فورم واحد مقسَّم بأقسام واضحة. كل قسم مخصص لدور معيّن:

| # | القسم | من يملؤه |
|---|-------|---------|
| 1 | بيانات المشروع | مدير المشروع |
| 2 | بيانات التنفيذ | مدير المشروع |
| 3 | قائمة التحقق — عمود المنسق | المنسق (أو مدير المشروع نيابةً عنه) |
| 4 | بيانات المراقب الميداني | مدير الرقابة العامة |
| 5 | قائمة التحقق — عمود المراقب + ملاحظات وتوصيات | المراقب |
| 6 | التقييم اليدوي (جودة، إغلاق، خصم) | مدير الرقابة العامة / الإدارة العامة |

كل قسم **يظهر أو يُخفى** حسب دور المستخدم وحالة المشروع. لا يمكن لمستخدم تعديل قسم لا يملك صلاحيته.

---

## دورة الاعتماد الإدارية (السلسلة الأولى)

تفصيل في [workflow-states.md](workflow-states.md). ملخص:

```
[مدير المشروع] ──creates──▶ projects (draft)
    │ يحدد المنسق
    ▼
[المنسق] ──fills coordinator column──▶ أو [مدير المشروع نيابةً عنه]
    │ (تُتخطّى إن كان مدير المشروع = المنسق)
    ▼
[مدير الدائرة] ──approves──▶
    ▼
[مدير الرقابة العامة] ──receives──▶ يحدد طريقة/مرحلة المراقبة ──▶ يعيّن مراقباً محدداً
    │                                                              🔶 عند التعيين تحديداً يتولّد monitoring_activity (primary) 🔶
    ▼
⟹ تبدأ دورة المراقبة (السلسلة الثانية في monitoring_activities)
```

---

## نقطة التوليد (لحظة إنشاء النشاط)

**[محدَّث]** عندما يُعيَّن المراقب تحديداً من مدير الرقابة العامة (لا قبل ذلك — حتى لو كان قد حدّد طريقة/مرحلة المراقبة مسبقاً):
1. يُنشأ سجل جديد في `monitoring_activities` بـ:
   - `source_type = 'project'`
   - `source_id = project.id`
   - `activity_role = 'primary'`
   - `reference_code` تلقائي بادئة `MP-`
   - `center_id`, `department_id`, `section_id` مستنسَخة من المشروع
   - `monitor_person_id` من اختيار مدير الرقابة
   - `workflow_status = 'in_progress'`
2. يُحدَّث `projects.primary_monitoring_activity_id` بالـ id الجديد
3. يُحدَّث `projects.workflow_status = 'monitoring_in_progress'`

---

## **[جديد]** عزل رؤية المراقب عن بيانات المنسق

عند عمل المراقب على النشاط، يجب ألا يرى بيانات أو هوية المنسق أو ما عبّأه بقائمة التحقق. هذا يجب أن يُطبَّق على مستوى الاستعلام/الـ Controller، وليس فقط بإخفاء عناصر في الواجهة (لتفادي تسرّب البيانات عبر HTML/Response مباشرة):
- أي View/Route مخصصة لشاشة المراقب (مثل صفحة "عمل المراقب على المشروع") يجب أن تُعيد فقط: بيانات المشروع الفعلية (اسم، تنفيذ، مستفيدين...)، وعمود المراقب في قائمة التحقق (`monitor_value` لكل بند)، **دون** `coordinator_id` أو `coordinator_value`.
- يُقترح تصميمياً: دالة/Resource مخصصة (مثل `Project::forMonitorView()` أو Controller منفصل لعرض المراقب) بدل استخدام نفس الـ Resource/View العام للمشروع مع إخفاء أعمدة بالـ Blade فقط.
- يُراعى هذا عند تصميم `ProjectPolicy` (المراقب لا يُخوَّل الوصول لحقول المنسق أصلاً، ليس فقط عدم عرضها).

---

## **[جديد]** حقول الرفض

بنية الحقول الكاملة في [checklist-schema.md](checklist-schema.md) (قسم "سادساً"). ملخص القرار:
- الرفض ممكن ضمن سلسلة الاعتماد الإدارية (خطوات مدير الدائرة / مدير الرقابة العامة).
- صلاحية الرفض `projects.reject` هي **ability مستقلة** — الحامل الافتراضي حالياً مدير الرقابة العامة، لكنها قابلة للمنح لأدوار أخرى لاحقاً عبر نظام الصلاحيات دون تعديل بنيوي (وليست مقيَّدة بالكود لدور معيّن).
- عند الرفض: تسجيل إلزامي لـ `rejection_reason` (نص)، `rejected_by` (مرجع مستخدم)، `rejected_at` (تاريخ)، و`gap_owner` (مسؤولية النقص — قيمة من قائمة قصيرة قابلة للتوسّع).
- **القرار بعد الرفض متروك بالكامل لتقدير الجهة الرافضة:** قد تُعيد `workflow_status` لحالة سابقة (مثل `pending_coordinator`) للتصحيح، أو تُبقيه/تُمرّره كما هو رغم النقص. لا يفرض الكود مساراً واحداً إلزامياً (انظر [workflow-states.md](workflow-states.md)).

---

## حساب نسبة الجاهزية

التفاصيل في [checklist-schema.md](checklist-schema.md). ملخص المنطق:

- لكل مجموعة: `نسبة_المجموعة = (عدد "جاهز" + 0.5 × عدد "جزئي") ÷ (عدد البنود − عدد "غير مطلوب")`
- النسبة الإجمالية: **متوسط بسيط** لنِسَب المجموعات (مطابق `AVERAGE` في Excel)
- **النسبة الإجمالية لعمود المراقب = execution_value في monitoring_activities**
- **حالة الجاهزية المشتقة** (3 مستويات، من عمود المراقب): موجود "غير جاهز" → 🔴 موقوف / موجود "جزئي" → 🔶 جاهز جزئياً / غير ذلك → ✅ جاهز للتنفيذ

---

## الصلاحيات الجديدة في `data/abilities.php`

```php
'projects' => [
    'name' => 'المشاريع',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
    'delete' => 'حذف',
    'approve_department' => 'موافقة مدير الدائرة',    // مدير الدائرة فقط
    'fill_coordinator' => 'تعبئة عمود المنسق',        // المنسق أو مدير المشروع نيابةً
    'fill_monitor' => 'تعبئة عمود المراقب',           // المراقب فقط
    'reject' => 'رفض المشروع',                        // ability مستقلة — افتراضياً مدير الرقابة العامة، قابلة للمنح لغيره لاحقاً
],
'checklist_admin' => [
    'name' => 'إدارة قائمة التحقق',
    'manage' => 'إدارة المجموعات والبنود',            // أدمن النظام فقط
],
```

---

## نقاط معلّقة — بانتظار قرار العميل

> **نقطة 1 (open-questions):** رقم المشروع — الصيغة النهائية وآلية التوليد لم تُحسم.
> **الافتراض المؤقت:** رقم تسلسلي تلقائي بسيط (`project_number` integer auto-increment)، قابل للتعديل اليدوي مع unique constraint. يسهل تغيير الصيغة لاحقاً.

> **نقطة 3 (open-questions):** **[محسوم جزئياً]** الرفض محسوم من ناحية المبدأ والحقول (انظر قسم "حقول الرفض" أعلاه). المتبقي معلّقاً فقط: القائمة الكاملة لفئات `gap_owner`، وحدود منح صلاحية `reject` لأطراف إضافية غير مدير الرقابة العامة.

> **نقطة 5 (open-questions):** بنود الموارد البشرية — بيانات توثيقية نصية فقط (has_person_field=true) بدون أي منطق تكليف فعلي. ستُناقش مع العميل مستقبلاً.

> **نقطة 6 (open-questions):** نسبة الجاهزية في جدول monitoring_activities مُنعكسة تلقائياً من عمود المراقب (computed/synced)، وليست حقلاً منفصلاً للإدخال اليدوي.

---

## الملفات المتأثرة (للتنفيذ بـ Cursor)

- Migrations: `projects`, `checklist_groups`, `checklist_items`, `project_checklist_values`
- Models: `Project`, `ChecklistGroup`, `ChecklistItem`, `ProjectChecklistValue`
- Policies: `ProjectPolicy`, `ChecklistAdminPolicy`
- تعديل: `data/abilities.php`
- Controller: `ProjectController`, `ChecklistAdminController`
- Routes: موارد projects + checklist admin
- Views (Blade): قائمة المشاريع، فورم المشروع (مقسَّم بأقسام)، صفحة التفاصيل مع قائمة التحقق

---

## قائمة المهام

| # | المهمة | الحالة |
|---|--------|--------|
| 1 | إنشاء migration جدول `projects` (كامل الحقول) | ✅ منجز |
| 2 | إنشاء migration جدولَي `checklist_groups` و`checklist_items` | ✅ منجز |
| 3 | إنشاء migration جدول `project_checklist_values` | ✅ منجز |
| 4 | إنشاء Seeders لمجموعات قائمة التحقق الأولية | ✅ منجز (`ChecklistSeeder`) |
| 5 | Models + Policies لكل الجداول أعلاه | ✅ منجز (`ProjectPolicy` أضيفت؛ `checklist_admin` بلا Policy مخصصة — استُخدم `Gate::define('checklist_admin.manage', ...)` في `AppServiceProvider` بنفس نمط `reports.view`، لأنه لا يوجد Model باسم ChecklistAdmin يمكن الاشتقاق منه تلقائياً) |
| 6 | تحديث `data/abilities.php` بمجموعتَي projects وchecklist_admin | ✅ منجز |
| 7 | Controller: قائمة المشاريع، إنشاء، تعديل، تفاصيل | ✅ منجز (`ProjectController`) |
| 8 | فورم المشروع: أقسام واضحة، حقول مترابطة (dependent dropdowns) | 🔶 جزئي — الأقسام موجودة وتعمل، لكن لم تُنفَّذ فلترة JS متسلسلة (مركز→دائرة→قسم)؛ الحقول الثلاثة قوائم كاملة مستقلة حالياً |
| 9 | منطق workflow: أزرار الانتقال (إرسال، موافقة، تعيين مراقب) | ✅ منجز ومُختبَر (feature tests) |
| 10 | منطق التخطّي التلقائي (مدير المشروع = المنسق) | ✅ منجز ومُختبَر |
| 11 | منطق توليد monitoring_activity عند وصول المشروع لمدير الرقابة | ✅ منجز ومُختبَر (عند تعيين المراقب تحديداً، بادئة `MP-`) |
| 12 | حساب نسب الجاهزية (بمجموعة وإجمالي) + عرضها | ✅ منجز ومُختبَر (`Project::recalculateReadiness()`) |
| 13 | نقل قيمة تنفيذ المراقب → execution_value في monitoring_activity | ✅ منجز ومُختبَر |
| 14 | واجهة أدمن قائمة التحقق (إضافة/تعديل مجموعات وبنود) | ✅ منجز (`ChecklistAdminController` + view، ترتيب عبر أزرار ↑/↓ بدل drag-and-drop) |
| 15 | صلاحية "تعبئة نيابةً عن المنسق" لمدير المشروع | ✅ منجز عبر نظام الصلاحيات (ability `projects.fill_coordinator` قابلة للمنح لمدير المشروع أيضاً، وليست مقيَّدة بالكود لدور واحد) |
| 16 | **[جديد]** حقول الرفض (rejection_reason، rejected_by، rejected_at، gap_owner) + صلاحية `projects.reject` المستقلة | ✅ منجز ومُختبَر |
| 17 | **[جديد]** استعلام/View مخصص لشاشة المراقب يُخفي بيانات المنسق على مستوى الكود (لا الواجهة فقط) | ✅ منجز ومُختبَر (`ProjectController::monitorWork()` + `monitor-work.blade.php` — لا يُحمَّل `coordinator_id`/`coordinator_value` إطلاقاً) |
| 18 | **[جديد]** دعم إنشاء أنشطة تابعة (activity_role='secondary') على مستوى البنية فقط — بدون واجهة تحويل فعلية الآن | ✅ منجز (`Project::secondaryMonitoringActivities()` + عمود `activity_role` من المرحلة 2، بلا واجهة تحويل بعد كما هو مخطَّط) |
| 19 | قائمة تنقّل raqib كاملة (مشاريع + نشاطات + أساس + إعدادات) | ✅ منجز (2026-07-02) |
| 20 | تسجيل `ChecklistSeeder` في `DatabaseSeeder` | ✅ منجز |
| 21 | عزل بيانات المنسق في `show` + تحويل المراقب لـ `monitorWork` | ✅ منجز |
| 22 | صلاحيات دقيقة (`set_monitoring_info` / `assign_monitor`) + قوائم ثوابت للمراقبة | ✅ منجز |

**ملاحظة تنفيذ (2026-07-02):** المرحلة 3 مُغلَقة وظيفياً بالكامل ما عدا البند 8 (تحسين UX بفلترة JS متسلسلة للقوائم، غير حرج للوظيفة). تم اختبار السلسلة الكاملة عبر `tests/Feature/ProjectsSmokeTest.php`. **مراجعة 2026-07-02:** أُعيد بناء قائمة التنقّل لـ raqib، أُصلحت ثغرات العزل/الصلاحيات/البذور، وأُضيفت واجهة تأكيد اكتمال المرور للنشاطات.
