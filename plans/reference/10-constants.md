# 10 — الثوابت (Constants)

## آلية التخزين

جدول `constants` الموجود مسبقاً في الـ starter kit (`key` → `value` json). يُقرأ في الكود عبر:

```php
Constant::where('key', $key)->value('value')  // ثم json_decode
```

انظر helpers في `ProjectController` / `MonitoringActivityController`. **سجل التوثيق الرسمي لكل مفتاح واستخدامه:** `data/constants-registry.php` — أي مفتاح جديد يُضاف يجب توثيقه هناك أولاً.

Seeder: `ConstantsSeeder` (يُشغَّل ضمن `php artisan migrate --seed`).

## السلالم الأربعة (رقم دائماً هو المصدر، التسمية للعرض فقط)

| المفتاح | القيم |
|---|---|
| `scale_execution` | ممتاز=100 / جيد=80 / مقبول=60 / خطر=40 |
| `scale_quality` | ممتاز=100 / جيد=85 / مقبول=70 / ضعيف=50 |
| `scale_closure` | مكتمل=100 / جزئي=60 / معلّق=30 / مفتوح=0 |
| `scale_deduction` | لا خصم=0 / تأخير=−5 / عجز=−10 / جودة=−15 / امتثال=−20 / مخالفة=−25 |

**اصطلاح الخصم المثبَّت:** يُخزَّن **سالباً** أو صفراً، ومعادلة KPI **تجمعه** مباشرة (`+ deduction_value`). هذا يخالف صياغة بعض ملفات التخطيط القديمة التي ذكرت "− الخصم" مع قيم سالبة (تناقض داخلي في `archive/v1/02-data-model.md` كان سيقلب الخصم لمكافأة لو طُبِّق حرفياً) — **الصحيح النهائي هو: سالب + جمع.**

## سلّم تصنيف KPI (`scale_kpi`)

≥98 ممتاز جداً · ≥90 ممتاز · ≥75 جيد · ≥60 مقبول · ≥40 ضعيف · <40 خطر شديد.

```
kpi_value = (execution_value×0.4) + (quality_value×0.3) + (closure_value×0.3) + deduction_value
```

## قوائم أخرى

| المفتاح | المحتوى |
|---|---|
| `activity_types` | 10 قيم من ورقة Excel الأصلية: تفتيش ميداني، جودة خدمة، فحص سلامة، فحص مخزون، متابعة شكاوى، مراجعة إجراءات، جرد مفاجئ، مراجعة عقود، تدقيق مالي، متابعة حضور |
| `monitoring_methods` | قيم Excel كنقطة بداية (`["ميداني"]`) — **معلّق:** القائمة الكاملة لم تُحسم نهائياً مع العميل، انظر [../pending-decisions.md](../pending-decisions.md) |
| `monitoring_stages` | قيم Excel كنقطة بداية (`["أثناء التنفيذ"]`) — نفس الملاحظة أعلاه |
| `checklist_options` | `ready`/`partial`/`not_ready`/`not_required` — انظر [09-checklist-and-readiness](09-checklist-and-readiness.md) |
| `source_types` | `project` / `external` / `meeting` (الأخير مؤجَّل لكن أُضيف مسبقاً لمرونة التوسع) |
| `project_types` | أنواع المشاريع (تُستخدم في فورم المشروع) |

كلا `monitoring_method` و`monitoring_stage` مصمَّمان كحقول `string` عادية (وليس enum بقاعدة البيانات)، تُملأ من قائمة منسدلة تستمد قيمها من `constants` — يسهل إضافة قيم جديدة لاحقاً دون تغيير Schema، بانتظار القائمة الكاملة من العميل.
