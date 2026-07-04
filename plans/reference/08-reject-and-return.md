# 08 — الرفض والإرجاع

## المبدأ

الرفض ممكن ضمن سلسلة اعتماد المشروع الإدارية ([06-project-approval-workflow](06-project-approval-workflow.md)). صلاحية `projects.reject` **ability مستقلة** (انظر [04-permissions](04-permissions.md)) — الحامل الافتراضي حالياً مدير الرقابة العامة (وعملياً مدير الدائرة أيضاً عند مراجعته)، لكنها قابلة للمنح لأي دور آخر عبر `role_user` دون أي تعديل بنيوي.

## التوحيد as-built: `return_target`

القرار الأقدم في التخطيط تصوَّر حالة `rejected` عامة واحدة مع حقول سبب/من/تاريخ فقط. **التطبيق الفعلي وحّد الرفض والإرجاع في مفهوم واحد** عبر حقل `return_target` (مضاف بـ migration `2026_07_04_120000_add_return_target_to_projects_table`) الذي يحدد **إلى أين تحديداً** يعود المشروع، لا مجرد "مرفوض":

```php
Project::returnTargetOptionsForRejector(?Person $person, bool $superAdmin = false): array
```

خيارات `return_target`:

| القيمة | المعنى | الحالة الناتجة |
|---|---|---|
| `return_project_manager` | إرجاع لمدير المشروع (مسودة) | `draft` |
| `return_coordinator` | إرجاع للمنسق (تعبئة) | `coordinator_filling` |
| `return_department_manager` | إرجاع لمدير الدائرة (موافقة) | `pending_dept_manager` |
| `reject_final` | رفض قاطع نهائي (لا إرجاع، لا أزرار متابعة) | `rejected` |

**قاعدة صارمة:** القرار بعد الرفض (أي من الخيارات الأربعة) متروك بالكامل لتقدير الجهة الرافضة عبر modal الرفض — لا يفرض الكود مساراً واحداً إلزامياً.

## الحقول الإلزامية عند أي رفض/إرجاع

`rejection_reason` (نص إلزامي)، `rejected_by` (FK `users`)، `rejected_at` (تاريخ)، `gap_owner` (مسؤولية النقص — قيمة من قائمة قصيرة قابلة للتوسّع، تُخزَّن كنص وليس enum صارم؛ Rule::in ديناميكي في `ProjectController` وليس enum ثابت بقاعدة البيانات).

## Routes

- `reject` — يفتح modal الرفض/الإرجاع الموحّد، يحفظ `return_target` + الحقول الإلزامية أعلاه، ويغيّر `workflow_status` وفق الجدول أعلاه.
- `reroute` — مسار أقدم لإعادة التوجيه بعد الرفض، **ما زال موجوداً للتوافق** (`super_admin`/`admin` فقط) — لا يُستخدم في المسار العادي بعد توحيد `return_target`.

## عرض حالة الرفض

`return_target` يُحفظ ويُستخدم لعرض تنبيه "أُرجِع المشروع [إلى الجهة كذا]" في صفحة `show` — بدل مجرد إظهار badge "مرفوض" عام بلا سياق.

## ما زال معلّقاً

القائمة الكاملة لفئات `gap_owner` (هل تقتصر على المنسق أم تشمل أطرافاً أخرى؟) وحدود منح `projects.reject` لأدوار إضافية غير مدير الرقابة/مدير الدائرة — لم تُحسم نهائياً مع العميل. انظر [../pending-decisions.md](../pending-decisions.md).
