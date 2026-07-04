# 02 — نموذج البيانات (as-built)

> راجع [01-architecture](01-architecture.md) للعلاقات المعمارية العامة. هذا الملف يفصّل الأعمدة فعلياً كما في migrations الحالية. للتفاصيل السطرية الدقيقة ارجع دائماً لملفات `database/migrations/` نفسها.

## نظرة عامة على العلاقات

```
people ──┬── users [علاقة اختيارية nullable: people.user_id]
         └── يُستخدم كمرجع انتقاء في: مسؤول النشاط، مدير المشروع، المنسق، مدير الدائرة، المراقب

projects ── 1:N ── monitoring_activities (عبر source_type='project' + source_id، activity_role يميّز primary/secondary)

centers ── 1:N ── departments ── 1:N ── sections

constants (key/value json) ── السلالم الأربعة، طريقة/مرحلة المراقبة، خيارات checklist، أنواع النشاط
```

## 1. `monitoring_activities`

### هوية ومصدر
| العمود | النوع | ملاحظة |
|---|---|---|
| `reference_code` | string unique | تلقائي (بادئة `MP-` للمشاريع)، قابل للتعديل يدوياً، تفرّد مفروض |
| `source_type` | string | `project` / `external` / (لاحقاً `meeting`) |
| `source_id` | unsignedBigInt nullable | بدون FK constraint حقيقي (polymorphic-like)، **بدون unique** — عدة أنشطة لنفس المصدر مسموح |
| `activity_role` | string | `primary` / `secondary`، default `primary` |

### هرم تنظيمي وأطراف
`center_id`, `department_id` (لا nullable)، `section_id` (nullable)، `responsible_person_id` (FK `people`، nullable)، `monitor_person_id` (FK `people`، nullable).

### زمن
`activity_date`, `activity_time`. **لا يوجد** عمود يوم/شهر/سنة مخزَّن — تُشتق بـ accessors من `activity_date` عند الحاجة.

### تصنيف ومحتوى
`activity_type` (من constants)، `funder_id` (FK `funders`)، `subject`, `notes`, `field_problem` (bool)، `action_taken`.

### حقول بيانات المشروع (تظهر فقط لو `source_type=project`)
مدير المشروع، عدد المستفيدين، مناطق التنفيذ، الميزانية، نسبة/حالة الجاهزية، التوصيات — **كلها اختيارية** على مستوى النشاط، مطلوبة فقط إذا عُبّئ أحدها (تطابق `COUNTA(O:U)>0` في Excel الأصلي).

### تقييم (4 حقول أرقام دائماً)
`execution_value`, `quality_value`, `closure_value`, `deduction_value` — decimal(5,2). `deduction_value` يُخزَّن **سالباً أو صفراً** (−5/−10/−15/−20/−25)، ومعادلة KPI تجمعه مباشرة.

### محسوب (مخزَّن للأداء)
- `kpi_value` = `(execution×0.4) + (quality×0.3) + (closure×0.3) + deduction` — يُعاد حسابه ويُخزَّن عند أي تعديل على القيم الأربعة.
- `kpi_rating` مشتق من `kpi_value` وسلّم `scale_kpi` في constants — يُخزَّن أيضاً.
- `is_verified`/`verification_status` — **accessor فقط، غير مخزَّن**، يفحص: صحة الهرم، تناقض الخصم (ثنائي الاتجاه)، تناقض الإغلاق (تنفيذ=100 وجودة=100 لكن إغلاق≠مكتمل)، اكتمال الحقول الإلزامية. **لا يمنع الحفظ أبداً.**

### مراقبة وحالة
`monitoring_method`, `monitoring_stage` (يحددهما مدير الرقابة فقط، من constants)، `workflow_status` (string، انظر [07-monitoring-workflow](07-monitoring-workflow.md))، `is_passage_complete` (bool)، `passage_completed_at`, `passage_completed_by`.

### نظام
`created_by`, `updated_by` (FK `users`)، timestamps.

## 2. `projects`

### بيانات المشروع
`project_name`, `project_number` (`string`/VARCHAR(50) — كان `integer` في أول migration ثم حُوِّل إلى نص بصيغة `P-{n}` عبر migration `2026_07_02_140000_enhance_projects_coordinator_and_number`، تسلسلي auto مع فرض uniqueness — انظر [../pending-decisions.md](../pending-decisions.md))، `project_type`, `funder_id`, `procurement_rep` (نص حر — ليس FK)، `project_manager_id` (FK `people`، لا nullable)، `coordinator_id` (FK `people`، nullable)، `coordinator_external_name` (نص، لوضع `external` — انظر [06](06-project-approval-workflow.md))، `center_id/department_id/section_id`، تواريخ التنفيذ، `location`.

### بيانات التنفيذ
`target_beneficiaries`, `execution_zones`, `estimated_duration` (نص حر)، `allocated_budget`.

### بيانات المراقب الميداني (تُملأ في مرحلة المراقبة)
`monitor_person_id`, `monitoring_date`, `monitoring_method`, `monitoring_stage`.

### نتائج قائمة التحقق (محسوبة، مخزَّنة)
`coordinator_readiness_pct`, `monitor_readiness_pct` — الأخير يُنسخ إلى `monitoring_activities.execution_value` للنشاط الأساسي (انظر [09-checklist-and-readiness](09-checklist-and-readiness.md)).

### ملاحظات وتوصيات
`monitor_notes`, `monitor_recommendations` — json arrays.

### سير العمل والتوقيعات
`workflow_status` (انظر [06](06-project-approval-workflow.md))، `primary_monitoring_activity_id` (FK nullable → monitoring_activities، يُملأ فقط عند توليد النشاط الأساسي)، طوابع/مراجع الإرسال والموافقة (`coordinator_submitted_at/by`, `dept_manager_approved_at/by`, `monitoring_manager_received_at/by`).

### الرفض/الإرجاع (as-built، وحّد لاحقاً حقل واحد)
`rejection_reason`, `rejected_by`, `rejected_at`, `gap_owner` + **`return_target`** (مضافة بـ migration `2026_07_04_120000_add_return_target_to_projects_table` لتوحيد "رفض قاطع" مقابل "إرجاع لجهة محددة" — انظر [08-reject-and-return](08-reject-and-return.md)).

### نظام
`created_by`, `updated_by`, timestamps.

## 3. `people`

جدول موحّد لكل شخص (له حساب دخول أم لا). `name` (إلزامي)، `user_id` (FK nullable → `users`)، `role` (صفة وظيفية واحدة — انظر [03-roles-and-org](03-roles-and-org.md))، `department_id` (الانتماء التنظيمي، ضروري لأن سلسلة الاعتماد تعتمد عليه)، `job_title`, `organization`, `phone` (كلها nullable).

**قاعدة صارمة:** أي اختيار شخص بالنظام = FK من هذا الجدول عبر قائمة/بحث، لا نص حر.

## 4. الهرم التنظيمي

`centers` (مركزان أساسيان: الجمعية، المراكز الصحية) → `departments` (FK `center_id`) → `sections` (FK `department_id`). كل مستوى تابع لمستوى أعلى واحد فقط.

## 5. `checklist_groups` / `checklist_items` / `project_checklist_values`

تفصيل كامل في [09-checklist-and-readiness](09-checklist-and-readiness.md).

## 6. `funders`

`name` فقط حالياً (قابل للتوسعة لاحقاً بلا قيود تصميمية).

## الجداول الموجودة مسبقاً في الكود (من الـ starter kit، لا تُعدَّل بنيتها إلا بإضافة FK عند الضرورة)

| الجدول | الغرض في raqib |
|---|---|
| `users` | حسابات الدخول — يُربط به `people.user_id` |
| `role_user` (migration باسم `role_users`، الموديل `RoleUser` يحدد `$table='role_user'`) | تخزين صلاحيات كل مستخدم — صفوف `role_name` = ability string |
| `constants` | key/value json — الثوابت (انظر [10-constants](10-constants.md)) |
| `activity_logs` | audit trail عام عبر `LogLastUserActivity` — **لا علاقة له بـ `monitoring_activities`** |
| `currencies` | غير مستخدم في raqib — تجاهله |
