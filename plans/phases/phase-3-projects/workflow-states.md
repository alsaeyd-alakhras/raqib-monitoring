# workflow-states.md — حالات سير العمل

## السلسلة الأولى: `projects.workflow_status`

تتبع موقع المشروع في مسار الاعتماد الإداري فقط.

| الحالة (قيمة في DB) | المعنى | من يُغيّر لهذه الحالة |
|--------------------|--------|----------------------|
| `draft` | مشروع جديد، ينشئه مدير المشروع ولم يُرسَل بعد | مدير المشروع عند الإنشاء |
| `pending_coordinator` | بانتظار تعبئة المنسق | مدير المشروع بعد حفظ القسم الأول والضغط على "إرسال للمنسق" |
| `coordinator_filling` | المنسق يعمل على عمود قائمة التحقق | (تلقائي إذا كان للمنسق حساب ودخل النظام) أو مدير المشروع يعبّئ نيابةً مباشرة |
| `pending_dept_manager` | بانتظار موافقة مدير الدائرة | المنسق أو مدير المشروع (نيابةً) عند الضغط على "إرسال لمدير الدائرة" |
| `pending_monitoring_manager` | بانتظار مدير الرقابة العامة | مدير الدائرة عند الضغط على "موافقة وإرسال" |
| `monitoring_in_progress` | المشروع في مرحلة المراقبة — تولّد النشاط | مدير الرقابة العامة عند تعيين مراقب |

**[محدَّث — as-built] حالة الرفض/الإرجاع — موحّدة عبر `return_target`:**
> **المصدر الحي لهذه الآلية هو [`reference/08-reject-and-return.md`](../../reference/08-reject-and-return.md).** الوصف أدناه سجل تاريخي مُصحَّح ليطابق الكود الحالي.
- الرفض ليس مجرد حالة `rejected` عامة — التطبيق الفعلي وحّد "الرفض القاطع" مع "الإرجاع لجهة محددة" في حقل واحد **`return_target`** (migration `2026_07_04_120000_add_return_target_to_projects_table`)، يحدده صاحب صلاحية `projects.reject` عبر modal الرفض:
  - `return_project_manager` → `draft`
  - `return_coordinator` → `coordinator_filling`
  - `return_department_manager` → `pending_dept_manager`
  - `reject_final` → `rejected` (رفض قاطع، لا إرجاع)
- الحقول الإلزامية عند أي رفض/إرجاع: `rejected_by`, `rejected_at`, `rejection_reason`, `gap_owner` (انظر checklist-schema.md).
- القرار (أيّ الخيارات الأربعة) متروك بالكامل لتقدير الجهة الرافضة — لا يفرض الكود مساراً تلقائياً واحداً.

---

## السلسلة الثانية: `monitoring_activities.workflow_status`

تتبع النشاط الرقابي بعد توليده (موحّدة لكل الأنشطة).

انظر [phase-2-monitoring-activities/schema-details.md](../../phase-2-monitoring-activities/schema-details.md)

| الحالة | المعنى |
|--------|--------|
| `pending_monitor` | بانتظار مدير الرقابة لتعيين مراقب |
| `in_progress` | المراقب يعمل |
| `pending_confirmation` | المراقب أنهى، بانتظار تأكيد مدير الرقابة |
| `completed` | اكتمل — is_passage_complete = true |

---

## جدول الانتقالات الكاملة

### انتقالات المشروع (السلسلة الأولى)

```
draft
  │ [مدير المشروع] يضغط "إرسال للمنسق"
  │   [محدَّث as-built] submit-to-coordinator ينقل **دائماً** إلى pending_coordinator
  │   (أُزيل تخطّي الحالة تلقائياً في وضع self — راجع reference/06-project-approval-workflow.md)
  ▼
pending_coordinator
  │ [المنسق] يدخل النظام → coordinator_filling
  │ [مدير المشروع] يختار "تعبئة نيابةً" → coordinator_filling مباشرة
  ▼
coordinator_filling
  │ [المنسق / مدير المشروع] يضغط "إرسال لمدير الدائرة"
  ▼
pending_dept_manager
  │ [مدير الدائرة] يضغط "موافقة وإرسال"
  │ أو [صاحب صلاحية reject] يرفض ──▶ rejected (سبب + gap_owner مسجَّلان)
  │     └─▶ الجهة الرافضة تقرر: عودة لـ coordinator_filling للتصحيح، أو تجاوز/تمرير كما هو
  ▼
pending_monitoring_manager
  │ [مدير الرقابة العامة] يحدد طريقة/مرحلة المراقبة
  │ أو [صاحب صلاحية reject] يرفض ──▶ rejected (نفس المنطق أعلاه)
  ▼
  [مدير الرقابة العامة] يختار مراقباً محدداً ويضغط "تعيين وبدء المراقبة"
  │   → 🔶 عند هذا التعيين تحديداً يتولّد monitoring_activity (activity_role='primary') تلقائياً 🔶
  ▼
monitoring_in_progress (نهاية دور المشروع في السلسلة الأولى)
```

> ملاحظة: نقاط الرفض الممكنة أعلاه (بعد `coordinator_filling`، بعد `pending_dept_manager`) أمثلة توضيحية — الرفض ممكن من أي خطوة يملك صاحبها صلاحية `projects.reject`، دون حصر بنيوي على خطوة معيّنة.

### انتقالات النشاط (السلسلة الثانية)

```
pending_monitor (يُنشأ هنا عند توليد النشاط من مشروع أو إدخاله مباشرة)
  │ [مدير الرقابة] يعيّن مراقباً
  ▼
in_progress
  │ [المراقب] يضغط "إنهاء عملي"
  ▼
pending_confirmation
  │ [مدير الرقابة] يتحقق، يضغط "تأكيد اكتمال المرور"
  │   → is_passage_complete = true
  ▼
completed ✅
```

---

## الحالة الخاصة: وضع self (المنسق = مدير المشروع)

> **[محدَّث as-built]** التخطّي التلقائي القديم (`draft → coordinator_filling` مباشرة) **أُزيل**. اليوم `submit-to-coordinator` ينقل دائماً إلى `pending_coordinator` أولاً حتى في وضع `self`؛ الفرق أن مدير المشروع نفسه يعبّئ عمود المنسق ثم يرسل لمدير الدائرة (لا انتظار طرف آخر). راجع [`reference/06-project-approval-workflow.md`](../../reference/06-project-approval-workflow.md).

```
projects.coordinator_id == projects.project_manager_id  (وضع self)
    draft → (submit-to-coordinator) → pending_coordinator
    مدير المشروع يعبّئ عمود المنسق ← coordinator_filling
    ثم يرسل مباشرة لـ pending_dept_manager (لا خطوة موافقة منسق منفصلة)
```

---

## الحالة الخاصة: نشاط خارجي مباشر

```
لا مشروع ← لا سلسلة أولى
monitoring_activities.source_type = 'external'
monitoring_activities.source_id = null
monitoring_activities.workflow_status يبدأ مباشرة بـ pending_monitor
```

---

## ملاحظة حول قاعدة البيانات

كل `workflow_status` مُخزَّن كـ `string` (وليس enum في قاعدة البيانات) لسهولة إضافة حالات جديدة مستقبلاً دون migration schema change.

القيود الصالحة تُطبَّق على مستوى Laravel (`in:draft,pending_coordinator,...`).
