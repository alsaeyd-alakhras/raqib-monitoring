# 06 — سلسلة اعتماد المشروع (السلسلة 1)

> سلسلة إدارية بحتة تخص المشروع فقط، **قبل** أن يولّد نشاطاً يدخل سلسلة المراقبة ([07-monitoring-workflow](07-monitoring-workflow.md)). لا علاقة لها بالمراقبة الفعلية. أي نشاط لا مصدره مشروعاً (خارجي مباشر، لاحقاً محضر اجتماع) يتخطّى هذه السلسلة بالكامل.

## حالات `projects.workflow_status` (as-built، مطابقة للكود)

```
draft
  → pending_coordinator / coordinator_filling
  → pending_dept_manager
  → pending_monitoring_manager
  → monitoring_in_progress
  → pending_monitoring_confirmation
  → passage_complete
  أو rejected في أي مرحلة (gap_owner + return_target)
```

## الخطوات

### 1. إنشاء المشروع (`project_manager`)
ينشئ سجل مشروع، يملأ بيانات المشروع + بيانات التنفيذ، يحدد المنسق عبر `coordinator_mode` (انظر [03-roles-and-org](03-roles-and-org.md)). Route: `submit-to-coordinator` — **دائماً** ينقل الحالة إلى `pending_coordinator` (بوابة تعبئة مضافة لاحقاً — انظر أدناه).

### 2. تعبئة المنسق
- **وضع `person` بحساب دخول مختلف عن مدير المشروع:** المنسق يدخل، يعبّئ عموده بقائمة التحقق (`fill-coordinator`)، يرسل بزر صريح (`submit-to-dept-manager`).
- **وضع `person` بلا حساب دخول، أو تعبئة نيابةً مفعَّلة:** مدير المشروع يعبّئ عمود المنسق مباشرة (`coordinator_filled_by` يُسجَّل).
- **وضع `self`:** خطوة الموافقة المنفصلة **تُتخطّى بالكامل** — مدير المشروع يكمل تعبئة عمود المنسق ضمن نفس الجلسة، وينتقل العمل مباشرة لمدير الدائرة.
- **وضع `external`:** لا سجل `people`، مدير المشروع يعبّئ القائمة نيابةً باسم خارجي فقط (`coordinator_external_name`).

**بوابة تعبئة المنسق (as-built):** يُمنع الإرسال لمدير الدائرة قبل حفظ عمود المنسق فعلياً — تصحيح لاحق أضاف هذا الشرط الصريح على `submit-to-dept-manager`.

### 3. موافقة مدير الدائرة (`department_manager`)
مدير الدائرة **التي ينتمي إليها مدير المشروع تنظيمياً** (`Person.department_id` لمدير المشروع، وليس بالضرورة دائرة المشروع نفسه) — Route: `approve-department`. يمكنه أيضاً الرفض/الإرجاع (انظر [08-reject-and-return](08-reject-and-return.md)).

### 4. استلام مدير الرقابة العامة وتعيين مراقب (`monitoring_director`)
1. يحدد طريقة/مرحلة المراقبة المبدئية — Route: `set-monitoring-info`.
2. يعيّن مراقباً محدداً من فريقه — Route: `assign-monitor`.
3. **عند لحظة تعيين المراقب تحديداً (لا قبلها)** يتولّد النشاط الأساسي في `monitoring_activities` (تفصيل الآلية في [01-architecture](01-architecture.md))، وتنتقل حالة المشروع إلى `monitoring_in_progress`.

من هذه اللحظة: لا علاقة للمنسق أو مدير الدائرة بالنشاط إطلاقاً، وتبدأ [07-monitoring-workflow](07-monitoring-workflow.md).

## رقم المشروع

صيغة `P-{n}` (مثال: `P-1`). Helpers في `Project`: `generateProjectNumber()`, `isProjectNumberAvailable()`. تحقق AJAX: `GET projects/check-project-number`. الصيغة الدقيقة كانت نقطة معلّقة في التخطيط الأصلي؛ استقرت عملياً على هذا التسلسل البسيط (انظر [../pending-decisions.md](../pending-decisions.md) إن أُعيد فتح النقاش).

## Routes (ملخص، كلها تحت `projects/{project}/`)

| Route name | الغرض |
|---|---|
| `submit-to-coordinator` | إرسال للمنسق |
| `fill-coordinator` | حفظ قائمة تحقق المنسق + `coordinator_readiness_pct` |
| `submit-to-dept-manager` | إرسال لمدير الدائرة (محجوب حتى تعبئة المنسق) |
| `approve-department` | موافقة مدير الدائرة |
| `set-monitoring-info` | تحديد طريقة/مرحلة المراقبة |
| `assign-monitor` | تعيين مراقب + توليد النشاط الأساسي |
| `reject` / `reroute` | رفض/إعادة توجيه (انظر [08](08-reject-and-return.md)) |
