# 09 — قائمة التحقق الديناميكية وحساب الجاهزية

## البنية

```
checklist_groups          checklist_items           project_checklist_values
──────────────────        ────────────────────      ───────────────────────────────
id                         id                        id
name                       group_id (FK)             project_id (FK)
order                      name                       checklist_item_id (FK)
is_active                  has_person_field           coordinator_value
                           order                      monitor_value
                           is_active                  person_name
```

`unique(project_id, checklist_item_id)` على `project_checklist_values`.

## الديناميكية والإدارة

المجموعات والبنود ديناميكية بالكامل (إضافة/تعديل/إعادة ترتيب) عبر `ChecklistAdminController`، محصورة بصلاحية `checklist_admin.manage` (Gate يدوي — لا Policy لأنه لا model اسمه ChecklistAdmin، انظر [04-permissions](04-permissions.md)). الترتيب عبر أزرار ↑/↓ وليس drag-and-drop.

**تعطيل بند/مجموعة لا يحذف قيمها الموجودة** في `project_checklist_values` — فقط يُخفيها من الفورم للمشاريع الجديدة؛ البيانات القديمة تبقى للتاريخ.

المجموعات الأولية (seed، منسوخة من ورقة "تقرير مشروع" في `project.xlsx`): اللوجستيك والموارد، التحضيرات الميدانية، الموارد البشرية (بنودها `has_person_field=true` — تحمل اسم شخص كبيان توثيقي بحت، بلا أي منطق تكليف/إشعار فعلي).

## الخيارات الأربعة (`checklist_options` في constants)

`ready` (جاهز) / `partial` (جزئي) / `not_ready` (غير جاهز) / `not_required` (غير مطلوب). **تصحيح مقابل التخطيط الأصلي:** المرجع القديم كان يذكر ثلاثة خيارات فقط (بلا "جزئي") — الفعلي في enum الـ migration وفي معادلة Excel الأصلية أربعة خيارات.

- **`partial` وزنه 0.5** في حساب النسبة (نصف بند جاهز).
- **`not_required` يُستثنى تماماً من المقام** (لا يدخل في حساب النسبة، فلا ينقصها ظلماً).

## حساب الجاهزية (`Project::recalculateReadiness()`)

لكل مجموعة، لكل عمود (منسق/مراقب) على حدة:

```
نسبة_المجموعة = (عدد "ready" + 0.5 × عدد "partial") ÷ (إجمالي البنود − عدد "not_required")
```

ثم **متوسط بسيط (غير مرجَّح)** لنِسَب كل المجموعات النشطة = النسبة الإجمالية (`coordinator_readiness_pct` / `monitor_readiness_pct`). إن أراد العميل أوزاناً مستقبلاً، يُضاف عمود `weight` إلى `checklist_groups` — لا قيد تصميمي يمنع ذلك.

**النسبة المعتمدة رسمياً في KPI تُؤخذ من عمود المراقب فقط:**

```
monitoring_activities.execution_value = projects.monitor_readiness_pct
```

يُحدَّث تلقائياً كلما عدّل المراقب قائمة التحقق. عمود المنسق توثيقي/مقارنة فقط ولا يدخل حساب الأداء النهائي.

## حالة الجاهزية المشتقة (3 مستويات، من عمود المراقب)

```
يوجد "not_ready"  → 🔴 موقوف — يحتاج مراجعة
يوجد "partial"     → 🔶 جاهز جزئياً — يحتاج متابعة
غير ذلك            → ✅ جاهز للتنفيذ — موصى بالمتابعة
```

هذا الحقل **مشتق من `projects`، وليس إدخالاً يدوياً مستقلاً على مستوى النشاط** — إن ظهر في `monitoring_activities` فهو انعكاس (computed/synced).

## Partials في views

`_checklist_edit`, `_checklist_display`, `_checklist_styles`, `_coordinator_checklist`, `_project_summary`, `_reject_modal` — تحت `resources/views/dashboard/projects/`.
