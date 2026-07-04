# TASKS.md — المرجع الرئيسي للتقدّم العام

> هذا الملف فهرس مراحل المشروع (سجل تنفيذ تاريخي بالترتيب الزمني). لا يحتوي تفاصيل — التفاصيل موجودة في `overview.md` داخل كل مجلد مرحلة.
> **للحالة الحية الحالية للنظام (as-built)، اذهب إلى [`reference/00-overview.md`](reference/00-overview.md)** — هذا الملف يبقى للتاريخ فقط. حدّثه عند بدء أي مرحلة جديدة أو إكمالها.
> خريطة التنقّل الكاملة لمجلد `plans/` في [`README.md`](README.md).

---

## نظرة سريعة على المراحل

| # | المرحلة | الحالة | الملف |
|---|---------|--------|-------|
| 1 | الأساس — قاعدة البيانات، الثوابت، الأشخاص، الصلاحيات | ✅ مكتملة (migrations، models+policies، abilities.php، seeders، controllers+routes+views لكل الكيانات الخمسة — تحقّق `route:list` بدون أخطاء) | [phase-1-foundation/overview.md](phases/phase-1-foundation/overview.md) |
| 2 | النشاطات المركزية (monitoring_activities) | ✅ مكتملة (migration+model+policy+abilities+controller+views+routes — تحقّق end-to-end عبر migrate:fresh+seed+tinker على SQLite) | [phase-2-monitoring-activities/overview.md](phases/phase-2-monitoring-activities/overview.md) |
| 3 | المشاريع — الجدول، الفورم، دورة الاعتماد والمراقبة | ✅ مكتملة (workflow + checklist + smoke tests — راجع phase-3/overview.md) | [phase-3-projects/overview.md](phases/phase-3-projects/overview.md) |
| 4 | مراحل مستقبلية (محضر اجتماع، تقارير، إلخ) | 🔒 مؤجَّل | [phase-4-future/overview.md](phases/phase-4-future/overview.md) |

---

## التبعيات بين المراحل

```
المرحلة 1 (الأساس)
    ↓
المرحلة 2 (النشاطات)  ← تعتمد على: people + organizational + constants
    ↓
المرحلة 3 (المشاريع)  ← تعتمد على: monitoring_activities + people + organizational
    ↓
المرحلة 4 (مستقبلي)  ← تعتمد على: كل ما سبق
```

---

## الجداول الجديدة بالمشروع (ملخص)

| الجدول | المرحلة | ملاحظة |
|--------|---------|--------|
| `centers` | 1 | المراكز الرئيسية |
| `departments` | 1 | الدوائر (FK → centers) |
| `sections` | 1 | الأقسام/الإدارات (FK → departments) |
| `people` | 1 | الأشخاص الموحّد (FK اختياري → users) |
| `funders` | 1 | الممولون |
| `monitoring_activities` | 2 | النشاطات الرقابية المركزية |
| `projects` | 3 | المشاريع |
| `checklist_groups` | 3 | مجموعات قائمة التحقق (ديناميكية) |
| `checklist_items` | 3 | بنود قائمة التحقق (ديناميكية) |
| `project_checklist_values` | 3 | قيم قائمة التحقق لكل مشروع |

## الجداول الموجودة في الكود (لا تُعدَّل بنيتها إلا بإضافة FK عند الضرورة)

| الجدول | الغرض في raqib |
|--------|----------------|
| `users` | حسابات الدخول — يُربط به `people.user_id` |
| `role_user` | تخزين الصلاحيات الفردية لكل مستخدم |
| `constants` | تخزين الثوابت (السلالم، أنواع النشاط، إلخ) |
| `activity_logs` | audit trail عام — **لا علاقة له بـ monitoring_activities** |
| `currencies` | غير مستخدم في raqib — تجاهله |
