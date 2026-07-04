# schema-details.md — تفاصيل جدول `monitoring_activities`

## البنية الكاملة للجدول

### مجموعة 1: الهوية والمصدر

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `id` | bigint PK | لا | |
| `reference_code` | string | لا | unique — يُولَّد تلقائياً، قابل للتعديل |
| `source_type` | enum('project','external','meeting') | لا | نوع مصدر النشاط |
| `source_id` | unsignedBigInt nullable FK | نعم | معرّف المشروع أو المحضر؛ **null للأنشطة الخارجية** |
| `activity_role` | enum('primary','secondary') | لا | default: `primary`. **[جديد]** يميّز كون النشاط أساسياً (يتولّد تلقائياً عند تعيين المراقب على المشروع) أو تابعاً (يُنشأ يدوياً لاحقاً — تفصيل التحويل مؤجَّل، انظر `04-open-questions.md` نقطة 4ب) |

> ملاحظة FK: `source_id` لا يُضاف له FK constraint حقيقي لأن المصدر متعدد الجداول (polymorphic-like). يُتحقق من الوجود على مستوى الكود فقط.
>
> **[محدَّث] لا قيد تفرّد على `(source_type, source_id)`:** العلاقة بين المشروع والنشاط أصبحت **1:N** — عدة صفوف نشاط يمكن أن تتشارك نفس `source_id` (نفس المشروع)، يُميَّز بينها بـ `activity_role` (نشاط أساسي واحد + أنشطة تابعة غير محدودة).

---

### مجموعة 2: الهرم التنظيمي والأطراف

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `center_id` | FK → centers | لا | المركز الرئيسي |
| `department_id` | FK → departments | لا | الدائرة (مرتبطة بالمركز) |
| `section_id` | FK → sections | نعم | القسم/الإدارة (اختياري — قد لا يكون للنشاط قسم محدد) |
| `responsible_person_id` | FK → people | نعم | المسؤول عن النشاط (يختاره من يُنشئ النشاط) |
| `monitor_person_id` | FK → people | نعم | المراقب — يُحدَّد من مدير الرقابة العامة (مرحلة لاحقة من الـ workflow) |

---

### مجموعة 3: الزمن

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `activity_date` | date | نعم | تاريخ النشاط |
| `activity_time` | time | نعم | وقت النشاط |

**لا يوجد** عمود `day_name` أو `month` أو `year` — تُشتق في الكود عند الحاجة:
```php
// في Model:
public function getDayNameAttribute(): ?string {
    return $this->activity_date?->locale('ar')->dayName;
}
public function getMonthAttribute(): ?int {
    return $this->activity_date?->month;
}
// إلخ
```

---

### مجموعة 4: التصنيف

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `activity_type` | string | نعم | اختيار من constants['activity_types'] |
| `funder_id` | FK → funders | نعم | الممول (اختياري) |

---

### مجموعة 5: المحتوى الرقابي

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `subject` | text | نعم | الموضوع |
| `notes` | text | نعم | الملاحظة |
| `field_problem` | boolean | لا | default: false — هل يوجد مشكلة ميدانية؟ |
| `action_taken` | text | نعم | الإجراء المتخذ |

---

### مجموعة 6: حقول التقييم (4 — تُخزَّن كأرقام دائماً)

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `execution_value` | decimal(5,2) | نعم | مصدرها نسبة جاهزية المراقب من المشروع (أو 0 افتراضياً) |
| `quality_value` | decimal(5,2) | نعم | يُدخَل يدوياً من مدير الرقابة/الإدارة العامة |
| `closure_value` | decimal(5,2) | نعم | يُدخَل يدوياً |
| `deduction_value` | decimal(5,2) | نعم | يُدخَل يدوياً — **يُخزَّن سالباً أو صفراً** (مثل −15) |

> **اصطلاح الخصم (مثبّت):** القيمة تُخزَّن **سالبة** (−5، −10، −15، −20، −25) أو صفر، ومعادلة KPI **تجمعها** (`+ deduction_value`). هذا أنظف برمجياً (قيمة واحدة تعكس الأثر مباشرة).
> **العرض للمستخدم:** يُعرض الخصم بوضوح في الواجهة — التسمية + القيمة مع إشارة سالبة ظاهرة (مثل "خصم جودة: −15"). المستخدم لا يتعامل مع تعقيد التخزين؛ يختار من قائمة منسدلة (تسمية → قيمة سالبة).
> **تنبيه تعارض مصدري:** `02-data-model.md` متناقض داخلياً (يذكر قيماً سالبة + نص معادلة "− الخصم"؛ تطبيقهما حرفياً يحوّل الخصم لمكافأة). و`project.xlsx` يخزّن الخصم **موجباً** ويطرحه. **المعتمد نهائياً هنا: سالب + جمع.** لا تتبع أياً من الصيغتين المتعارضتين عند التنفيذ.

---

### مجموعة 7: الحقول المحسوبة (مخزّنة للأداء)

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `kpi_value` | decimal(5,2) | نعم | `(exec×0.4)+(qual×0.3)+(clos×0.3)+deduct` — يُحسب ويُخزَّن عند الحفظ |
| `kpi_rating` | string | نعم | مُشتق من kpi_value وسلّم KPI — **يُخزَّن** لتجنّب إعادة الحساب في كل عرض |

> **قرار تصميمي:** `kpi_value` و`kpi_rating` يُخزَّنان للأداء في القوائم والتقارير. يُعاد حسابهما تلقائياً عند أي تعديل على قيم التقييم الأربعة (via Model Observer أو `saving` event).

`is_verified` **لا يُخزَّن** — هو accessor فقط يُحسب في كل مرة.

---

### مجموعة 8: المراقبة وسير العمل

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `monitoring_method` | string | نعم | من constants['monitoring_methods'] — يُحدِّده مدير الرقابة فقط |
| `monitoring_stage` | string | نعم | من constants['monitoring_stages'] — يُحدِّده مدير الرقابة فقط |
| `workflow_status` | string | لا | حالة سير عمل المراقبة (انظر أدناه) |
| `is_passage_complete` | boolean | لا | default: false — يؤكده مدير الرقابة في النهاية |
| `passage_completed_at` | timestamp | نعم | متى أُكِّد اكتمال المرور |
| `passage_completed_by` | FK → users | نعم | من أكّد اكتمال المرور |

---

### حقول النظام

| العمود | النوع | nullable | ملاحظة |
|--------|------|----------|--------|
| `created_by` | FK → users | نعم | من أنشأ النشاط |
| `updated_by` | FK → users | نعم | آخر من عدّله |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

---

## قيم `workflow_status`

| القيمة | المعنى |
|--------|--------|
| `pending_monitor` | بانتظار تعيين مراقب من مدير الرقابة |
| `in_progress` | المراقب يعمل على النشاط |
| `pending_confirmation` | المراقب أنهى، بانتظار تأكيد نهائي من مدير الرقابة |
| `completed` | اكتمل المرور (is_passage_complete = true) |

> ملاحظة: حالة الرفض/الإعادة غير مضمّنة الآن. الـ enum/string يسمح بإضافة `rejected` لاحقاً دون تغيير Schema (من `04-open-questions.md`، نقطة 3).

---

## منطق is_verified (accessor في Model)

> هذا المنطق مستخرج من معادلة `✓ تحقق` الفعلية في `DATA` (العمود 4). يُرجع رسالة حالة (مثل "✗ هرم"، "✗ خصم"، "✗ ناقص: ..."، أو "✓") وليس boolean فقط — لعرض سبب عدم الاكتمال للمستخدم.

```php
public function getVerificationStatusAttribute(): string
{
    // 1. صحة الهرم التنظيمي: يجب أن يكون (center + department + section) تركيبة صحيحة مترابطة
    if (!$this->isValidHierarchy()) return '✗ هرم';

    // 2. منطق الخصم ثنائي الاتجاه (مطابق معادلة Excel)
    //    - مشكلة ميدانية = لا، لكن يوجد خصم → تناقض
    if (!$this->field_problem && $this->deduction_value && $this->deduction_value != 0) return '✗ خصم';
    //    - مشكلة ميدانية = نعم، لكن لا خصم → تناقض
    if ($this->field_problem && ($this->deduction_value === null || $this->deduction_value == 0)) return '✗ خصم';

    // 3. تناقض الإغلاق (مطابق Excel): تنفيذ ممتاز(100) + جودة ممتاز(100) لكن الإغلاق ليس مكتملاً(100)
    if ($this->execution_value == 100 && $this->quality_value == 100
        && $this->closure_value !== null && $this->closure_value != 100) return '✗ إغلاق';

    // 4. اكتمال الحقول الإلزامية (التاريخ، الوقت، الهرم، المسؤول، النوع، المحتوى، التقييمات)
    //    ملاحظة: حقول المشروع (مدير/مستفيدين/مناطق/ميزانية/جاهزية/توصيات)
    //    مطلوبة فقط إذا عُبّئ أيٌّ منها (مطابق شرط COUNTA(O:U)>0 في Excel).
    $missing = $this->collectMissingFields();
    if (count($missing) > 0) return '✗ ناقص: ' . implode('، ', $missing);

    return '✓';
}

// accessor مختصر boolean للفلترة السريعة
public function getIsVerifiedAttribute(): bool
{
    return $this->verification_status === '✓';
}
```

**النقاط الجوهرية المستخرجة من Excel:**
- تناقض الإغلاق (نقطة 3) **كان مفقوداً** في النسخة الأولى من الخطة — أُضيف الآن.
- شرط حقول المشروع "مطلوبة فقط إن عُبّئ أحدها" **كان مفقوداً** — أُضيف.
- الحالة تُرجع **سبب** عدم الاكتمال (وليس true/false فقط) لعرضه للمستخدم كما في Excel.

---

## معادلة KPI

```php
public function calculateKpi(): ?float
{
    if ($this->execution_value === null || $this->quality_value === null
        || $this->closure_value === null || $this->deduction_value === null) {
        return null;
    }
    return ($this->execution_value * 0.4)
         + ($this->quality_value * 0.3)
         + ($this->closure_value * 0.3)
         + $this->deduction_value; // سالب أو صفر
}
```

يُحسب ويُخزَّن في `kpi_value` عند كل `save()` عبر Model event.

---

## قواعد التحقق (Validation Rules) عند الحفظ

```
reference_code: required | string | unique:monitoring_activities,reference_code,{id}
source_type: required | in:project,external,meeting
activity_role: required | in:primary,secondary
center_id: required | exists:centers,id
department_id: required | exists:departments,id | تحقق أن department ينتمي لـ center_id
section_id: nullable | exists:sections,id | تحقق أن section ينتمي لـ department_id
responsible_person_id: nullable | exists:people,id
monitor_person_id: nullable | exists:people,id
activity_date: nullable | date
activity_time: nullable | date_format:H:i
activity_type: nullable | string
funder_id: nullable | exists:funders,id
field_problem: required | boolean
execution_value: nullable | numeric | min:0 | max:100
quality_value: nullable | numeric | min:0 | max:100
closure_value: nullable | numeric | min:0 | max:100
deduction_value: nullable | numeric | max:0 (سالب أو صفر)
monitoring_method: nullable | string
monitoring_stage: nullable | string
workflow_status: required | in:pending_monitor,in_progress,pending_confirmation,completed
is_passage_complete: required | boolean
```

**لا يوجد حقل مطلوب يمنع الحفظ الكلّي** — الهدف هو قبول النشاط الناقص وعرض حالة `is_verified` فقط.
