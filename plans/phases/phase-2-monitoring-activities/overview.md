# المرحلة 2 — النشاطات المركزية (Monitoring Activities)

> **سجل تنفيذ تاريخي.** المرجع الحي الحالي (as-built) هو `plans/reference/` — خصوصاً [01-architecture.md](../../reference/01-architecture.md) و[02-data-model.md](../../reference/02-data-model.md) و[07-monitoring-workflow.md](../../reference/07-monitoring-workflow.md). عند التعارض: الكود يفوز أولاً، ثم `reference/`.

## الهدف
إنشاء الجدول المحوري للنظام بأكمله: `monitoring_activities`. كل أنواع الأنشطة (من مشروع، خارجية مباشرة، مستقبلاً من محضر اجتماع) تُسجَّل هنا بنفس البنية الأساسية. يتضمن منطق حساب KPI، توليد رمز النشاط، وحقل `✓ تحقق` المحسوب.

**يعتمد على:** المرحلة 1 (people, organizational, constants)

---

## الجدول الرئيسي: `monitoring_activities`

> التفاصيل الكاملة للحقول في [schema-details.md](schema-details.md)

### ملخص المجموعات
1. **هوية ومصدر**: رمز النشاط، نوع المصدر، مرجع المصدر، **دور النشاط (activity_role: أساسي/تابع)**
2. **هرم تنظيمي وأطراف**: center_id، department_id، section_id، responsible_person_id، monitor_person_id
3. **زمن**: التاريخ، الوقت (اليوم/الشهر/السنة **لا تُخزَّن — تُشتق من التاريخ**)
4. **تصنيف**: نوع النشاط، funder_id
5. **محتوى رقابي**: الموضوع، الملاحظة، مشكلة ميدانية، الإجراء المتخذ
6. **تقييم (4 حقول أرقام)**: execution_value، quality_value، closure_value، deduction_value
7. **محسوب**: kpi_value (معادلة)، kpi_rating (مشتق من السلّم)، is_verified (computed display field)
8. **مراقبة وحالة**: monitoring_method، monitoring_stage، workflow_status، is_passage_complete

---

## قرارات تصميمية لهذه المرحلة

### **[محدَّث]** علاقة المشروع بالنشاط — 1:N
المشروع لم يعد يُنتج نشاطاً واحداً فقط. كل مشروع له **نشاط أساسي (primary)** واحد إلزامي يتولّد تلقائياً عند تعيين المراقب عليه (تفصيل التوقيت في `phase-3-projects/overview.md`)، وعدد غير محدود من **الأنشطة التابعة (secondary)** التي تُنشأ يدوياً لاحقاً (مثال: تحويل ملاحظة ميدانية لنشاط مستقل بضغطة زر). **بناء واجهة/منطق إنشاء الأنشطة التابعة مؤجَّل** — المطلوب في هذه المرحلة فقط أن بنية الجدول (`source_id` بدون تفرّد + `activity_role`) تدعم ذلك دون إعادة هيكلة لاحقاً.

### رمز النشاط (reference_code)
- **يُولَّد تلقائياً** عند إنشاء النشاط.
- **قابل للتعديل يدوياً** من قِبَل مدير الرقابة العامة أو أدمن النظام.
- **تفرّد إلزامي** (unique constraint في قاعدة البيانات + تحقق في الكود عند الحفظ والتعديل).

> **واقع Excel (مرجع):** الرمز هناك يُبنى من **رقم المشروع**: `{رقم_المشروع}/{تسلسل} - {رقم_الصف}` (مثل `912/1 - 5`)، أو `إداري/{تسلسل} - {رقم_الصف}` للأنشطة غير المرتبطة بمشروع.
>
> **معلّق — بانتظار العميل:** صيغة الرمز النهائية لم تُحسم (`00-instructions` نقطة 48). **الافتراض المؤقت المقترح:** بادئة حسب نوع المصدر (`MP-` مشروع، `MA-` خارجي، `MM-` محضر مستقبلاً) + تسلسل. صمِّم توليد الرمز في دالة واحدة قابلة للتبديل بسهولة (strategy)، حتى لو اختار العميد لاحقاً صيغة Excel (رقم مشروع/إداري) فالتغيير يكون في مكان واحد.

### حقل `✓ تحقق` — is_verified
**ليس حقلاً مخزَّناً في قاعدة البيانات** — هو accessor مُحسَب في الـ Model يُرجع **سبب** عدم الاكتمال (أو "✓"). المنطق الكامل المستخرج من معادلة Excel الفعلية في [schema-details.md](schema-details.md). ملخص الفحوصات:
1. صحة الهرم التنظيمي (تركيبة center+department+section صحيحة ومترابطة) → "✗ هرم"
2. منطق الخصم **ثنائي الاتجاه**: مشكلة=لا + خصم مضبوط = تناقض / مشكلة=نعم + لا خصم = تناقض → "✗ خصم"
3. **تناقض الإغلاق**: تنفيذ=100 و جودة=100 لكن إغلاق≠مكتمل → "✗ إغلاق"
4. اكتمال الحقول الإلزامية — وحقول المشروع مطلوبة **فقط إن عُبّئ أحدها** → "✗ ناقص: ..."

**لا يمنع الحفظ** — النشاط يُحفظ حتى لو لم يكن محقَّقاً.

### حساب KPI
```
kpi_value = (execution_value × 0.4) + (quality_value × 0.3) + (closure_value × 0.3) + deduction_value
```
ملاحظة: `deduction_value` قيمة سالبة أو صفر — الجمع يطرح تلقائياً.

`kpi_rating` يُشتق من `kpi_value` بمقارنته بـ `scale_kpi` من جدول `constants`.

---

## الصلاحيات الجديدة في `data/abilities.php`

```php
'monitoring_activities' => [
    'name' => 'النشاطات الرقابية',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
    'delete' => 'حذف',
    'assign_monitor' => 'تعيين مراقب',       // مدير الرقابة العامة فقط
    'set_monitoring_info' => 'تحديد طريقة/مرحلة المراقبة', // مدير الرقابة فقط
    'confirm_completion' => 'تأكيد اكتمال المرور',  // مدير الرقابة فقط
    'edit_ratings' => 'تعديل قيم التقييم',    // مدير الرقابة + الإدارة العامة
],
```

## توزيع الصلاحيات حسب الدور الوظيفي

| الدور | الصلاحيات |
|-------|-----------|
| أدمن النظام | super_admin=true (كل شيء) |
| الإدارة العامة | monitoring_activities.view + edit_ratings (عرض كل شيء، تعديل التقييمات) |
| مدير الرقابة العامة | monitoring_activities.view + create + update + assign_monitor + set_monitoring_info + confirm_completion + edit_ratings |
| مراقب | monitoring_activities.view (المسنَدة له فقط) + update (عمود المراقب فقط) |
| مدير مشاريع | monitoring_activities.view (المرتبطة بمشاريعه) |

---

## الملفات المتأثرة (للتنفيذ بـ Cursor)

- Migration جديدة: `monitoring_activities`
- Model: `MonitoringActivity` (مع accessor is_verified، accessor kpi_rating)
- Policy: `MonitoringActivityPolicy`
- تعديل: `data/abilities.php`
- Controller: `MonitoringActivityController`
- Routes: موارد monitoring-activities
- Views (Blade): قائمة، فورم إضافة/تعديل، عرض تفصيلي

---

## نقطة معلّقة

> **رقم 2 من open-questions:** طريقة المراقبة ومرحلة المراقبة — القائمة الكاملة لم تُحسم.
> **الافتراض المؤقت:** الحقلان `monitoring_method` و`monitoring_stage` من نوع `string` (وليس enum)، تُملأ من قائمة منسدلة يستمد قيمها من مفاتيح constants. يسهل إضافة قيم جديدة دون تغيير Schema.

---

## قائمة المهام

| # | المهمة | الحالة |
|---|--------|--------|
| 1 | إنشاء migration جدول `monitoring_activities` (كامل الحقول كما في schema-details.md) | ✅ تم — تحقّق فعلياً بتشغيل `migrate:fresh` كاملاً على SQLite (كل الـ FK نجحت) |
| 2 | إنشاء Model `MonitoringActivity` مع accessor kpi_rating + is_verified | ✅ تم — تحقّق فعلياً عبر tinker: kpi_value/kpi_rating/verification_status تعمل بشكل صحيح على سجل تجريبي |
| 3 | إنشاء Policy `MonitoringActivityPolicy` | ✅ تم |
| 4 | تحديث `data/abilities.php` بمجموعة monitoring_activities | ✅ تم (المفتاح الفعلي `monitoringactivities` بدون underscore — يطابق اشتقاق ModelPolicy من اسم الكلاس) |
| 5 | Controller: index (قائمة + فلترة)، create، store، edit، update (لا show — نفس نمط except(show) في مرحلة 1) | ✅ تم |
| 6 | منطق توليد reference_code تلقائياً (بالبادئة المناسبة لكل نوع مصدر) | ✅ تم — تحقّق فعلياً عبر tinker: `MP-1` ثم `MP-2` بشكل متسلسل صحيح |
| 7 | التحقق من تفرّد reference_code عند الحفظ والتعديل | ✅ تم (unique rule مع استثناء id عند التعديل) |
| 8 | Views: قائمة النشاطات (مع عرض KPI والتصنيف)، فورم إضافة/تعديل | ✅ تم — نمط Bootstrap بسيط (لا Yajra، حجم متوسط) |
| 9 | Dependent dropdowns: مركز → دائرة → قسم في فورم النشاط | ✅ تم بنفس تبسيط مرحلة 1 (قائمة كاملة معنونة باسم الأب، بدون AJAX) |
| 10 | عرض `✓ تحقق` بشكل واضح في القائمة وفي فورم التعديل | ✅ تم (badge أخضر/أصفر في index يعرض `verification_status`) |
| 11 | صلاحية تقييد تعديل النشاط بعد الإغلاق (مدير الرقابة والإدارة العامة فقط) | ✅ تم — يُمنع تعديل نشاط `workflow_status=completed` إلا لمن يملك ability `edit_ratings` |
| 12 | رابط التنقّل في القائمة الأفقية (`asideH`) | ✅ تم (2026-07-02 — مراجعة شاملة) |
| 13 | فلترة قائمة النشاطات + تحديد نطاق المراقب | ✅ تم |
| 14 | حقل `source_id` + تأكيد اكتمال المرور (`confirm_completion`) | ✅ تم |
| 15 | تسميات عربية لـ `workflow_status` + عرض التحقق في فورم التعديل | ✅ تم |
| 16 | إكمال منطق `is_verified` (الهرم + حقول التقييم) | ✅ تم |

> **[اكتشاف مهم أثناء التنفيذ]** مكوّن `<x-form.select>` فيه علة خفيّة إضافية عند استخدام مفاتيح int في prop `options` (تُصبح قيمة الـ option هي النص بدل المفتاح) — انظر تفاصيلها الكاملة في `CLAUDE.md` قسم "أخطاء متكررة". أثّرت هذه العلة على حقلَي `department_id`/`section_id` في هذه المرحلة وأيضاً في `sections/_form.blade.php` من المرحلة 1 (تم تصحيح الاثنين).
