# المرحلة 1 — الأساس (Foundation)

> **سجل تنفيذ تاريخي.** المرجع الحي الحالي (as-built) هو `plans/reference/` — خصوصاً [03-roles-and-org.md](../../reference/03-roles-and-org.md) و[04-permissions.md](../../reference/04-permissions.md) و[10-constants.md](../../reference/10-constants.md). عند التعارض: الكود يفوز أولاً، ثم `reference/`.

## الهدف
بناء القاعدة التي تعتمد عليها كل المراحل اللاحقة: الهرم التنظيمي، الأشخاص، الممولون، الثوابت، وتوسيع نظام الصلاحيات ليشمل أدوار raqib.

**هذه المرحلة لا تعتمد على أي مرحلة أخرى.**

---

## الجداول الجديدة

### 1. `centers` — المراكز الرئيسية
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `name` | string | اسم المركز |
| `timestamps` | | |

البيانات الأولية (seed): "الجمعية"، "المراكز الصحية" — مركزان فقط (مؤكَّد من `LISTS` A:C، إجمالي 68 قسماً).

---

### 2. `departments` — الدوائر
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `center_id` | FK → centers | cascadeOnDelete |
| `name` | string | اسم الدائرة |
| `timestamps` | | |

البيانات الأولية (seed):
- **ضمن "الجمعية"** (6 دوائر من Excel): سكرتاريا، دائرة الشؤون الإدارية، دائرة التنمية الاجتماعية، دائرة المشاريع والتسويق والإعلام، دائرة الشؤون المالية، دائرة الاستثمار الخيري.
- **ضمن "المراكز الصحية"** (3 وحدات/مستشفيات من Excel): يافا، الكويتي، الوسطى.
- **إضافة منّا (تنبيه):** "دائرة الرقابة العامة" **غير موجودة في هرم Excel** — نضيفها لأن المراقبين تابعون لها مباشرة (انظر `01-context.md`). إن رفض العميل وجودها كدائرة في الهرم، تُحذف بسهولة دون أثر بنيوي.

---

### 3. `sections` — الأقسام / الإدارات
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `department_id` | FK → departments | cascadeOnDelete |
| `name` | string | اسم القسم |
| `timestamps` | | |

البيانات الأولية: متاحة بالكامل من `LISTS` A:C في `project.xlsx` (68 صفاً: المركز ← الدائرة ← القسم). توزيع الأقسام الفعلي: سكرتاريا (2)، الشؤون الإدارية (5)، التنمية الاجتماعية (2)، المشاريع والتسويق والإعلام (12)، الشؤون المالية (4)، الاستثمار الخيري (7)، يافا (18)، الكويتي (6)، الوسطى (12). تُستورد كـ seed مرة واحدة.

---

### 4. `people` — الأشخاص الموحّد
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `name` | string | إلزامي |
| `user_id` | FK nullable → users | اختياري — فقط إن كان للشخص حساب دخول |
| `job_title` | string nullable | المسمى الوظيفي |
| `organization` | string nullable | الجهة إن كان خارجياً |
| `phone` | string nullable | |
| `timestamps` | | |

**قاعدة صارمة:** كل انتقاء لشخص في النظام (مسؤول نشاط، منسق، مدير...) يجب أن يكون من هذا الجدول، وليس نصاً حراً.

**seed أولي للمراقبين (من `LISTS` S):** 3 مراقبين جاهزون لهم حساب دخول + صلاحية مراقب: **سمير نصار، اياد أبو عبدو، محمد حسن**. يُنشأ لكل منهم سجل في `people` مربوط بسجل `users`.

---

### 5. `funders` — الممولون
| العمود | النوع | ملاحظة |
|--------|------|--------|
| `id` | bigint PK | |
| `name` | string | إلزامي |
| `timestamps` | | |

ملاحظة: الاسم فقط حالياً. صمِّم بدون قيود تمنع إضافة أعمدة مستقبلية (دولة، نوع تمويل، إلخ).

---

## تحديثات الكود الموجود

### تحديث `data/abilities.php`
إضافة المجموعات التالية (وإلغاء تعليق `constants`):

```php
// إلغاء تعليق:
'constants' => [
    'name' => 'ثوابت النظام',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
],

// إضافة جديدة:
'organizational' => [
    'name' => 'الهيكل التنظيمي',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
    'delete' => 'حذف',
],
'people' => [
    'name' => 'الأشخاص',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
    'delete' => 'حذف',
],
'funders' => [
    'name' => 'الممولون',
    'view' => 'عرض',
    'create' => 'اضافة',
    'update' => 'تعديل',
    'delete' => 'حذف',
],
// ستُضاف monitoring_activities وprojects في المرحلتين التاليتين
```

---

## ثوابت جديدة في جدول `constants`

تُضاف كـ seed data بمفاتيح محددة (key/value json). كل سلّم يُخزَّن كمصفوفة من `{value, label}`:

| المفتاح (key) | المحتوى (value — json) |
|---------------|------------------------|
| `scale_execution` | `[{value:100,label:"ممتاز"},{value:80,label:"جيد"},{value:60,label:"مقبول"},{value:40,label:"خطر"}]` |
| `scale_quality` | `[{value:100,label:"ممتاز"},{value:85,label:"جيد"},{value:70,label:"مقبول"},{value:50,label:"ضعيف"}]` |
| `scale_closure` | `[{value:100,label:"مكتمل"},{value:60,label:"جزئي"},{value:30,label:"معلّق"},{value:0,label:"مفتوح"}]` |
| `scale_deduction` | `[{value:0,label:"لا خصم"},{value:-5,label:"تأخير"},{value:-10,label:"عجز"},{value:-15,label:"جودة"},{value:-20,label:"امتثال"},{value:-25,label:"مخالفة"}]` |
| `scale_kpi` | `[{min:98,label:"ممتاز جداً"},{min:90,label:"ممتاز"},{min:75,label:"جيد"},{min:60,label:"مقبول"},{min:40,label:"ضعيف"},{min:0,label:"خطر شديد"}]` |
| `activity_types` | `["تفتيش ميداني","جودة خدمة","فحص سلامة","فحص مخزون","متابعة شكاوى","مراجعة إجراءات","جرد مفاجئ","مراجعة عقود","تدقيق مالي","متابعة حضور"]` |
| `monitoring_methods` | `["ميداني"]` ← **معلّق: القائمة الكاملة لم تُحسم — انظر أدناه** |
| `monitoring_stages` | `["أثناء التنفيذ"]` ← **معلّق: القائمة الكاملة لم تُحسم — انظر أدناه** |
| `checklist_options` | `["جاهز","جزئي","غير جاهز","غير مطلوب"]` — **"جزئي" وزنه 0.5 في حساب الجاهزية، "غير مطلوب" يُستثنى من المقام** (مستخرج من معادلة Excel الفعلية) |
| `source_types` | `["project","external","meeting"]` ← "meeting" مؤجل، لكن يُضاف الآن لمرونة التوسع |

---

## نقاط معلّقة — بانتظار قرار العميل

> **طريقة المراقبة ومرحلة المراقبة** (من `04-open-questions.md`، نقطة 2):
> البيانات الحالية تُظهر قيمة واحدة فقط لكل حقل. **الافتراض المؤقت:** نبدأ بهذه القيم كـ seed data في `constants`، ونصمّم واجهة الأدمن بحيث تسمح بإضافة قيم جديدة لهذين المفتاحين بسهولة فور وصول القائمة الكاملة من العميل.

---

## الملفات المتأثرة (للتنفيذ لاحقاً بـ Cursor)

- Migrations جديدة: `centers`, `departments`, `sections`, `people`, `funders`
- Models جديدة: `Center`, `Department`, `Section`, `Person`, `Funder`
- Policies جديدة: `PersonPolicy`, `FunderPolicy`, `OrganizationalPolicy`
- Seeders: `OrganizationalSeeder`, `ConstantsSeeder`
- تعديل: `data/abilities.php`
- Controllers جديدة: `OrganizationalController`, `PersonController`, `FunderController`
- Views (Blade): قوائم وفورم إضافة/تعديل لكل كيان

---

## قائمة المهام

| # | المهمة | الحالة |
|---|--------|--------|
| 1 | إنشاء migration جدول `centers` | ✅ تم |
| 2 | إنشاء migration جدول `departments` | ✅ تم |
| 3 | إنشاء migration جدول `sections` | ✅ تم |
| 4 | إنشاء migration جدول `people` | ✅ تم |
| 5 | إنشاء migration جدول `funders` | ✅ تم |
| 6 | إنشاء Models + Policies لكل الجداول أعلاه | ✅ تم (Policy لكل موديل منفصلة: Center/Department/Section/Person/Funder — انظر CLAUDE.md) |
| 7 | تحديث `data/abilities.php` بالمجموعات الجديدة | ✅ تم (centers/departments/sections منفصلة بدل organizational واحدة — انظر CLAUDE.md) |
| 8 | إنشاء `ConstantsSeeder` بكل الثوابت المذكورة | ✅ تم |
| 9 | إنشاء `OrganizationalSeeder` (مركزان + 9 دوائر + 68 قسماً من LISTS A:C، مُتحقَّق فعلياً من الملف) | ✅ تم |
| 9b | seed المراقبين الثلاثة في `people` + `users` | ✅ تم |
| 10 | Controllers + Routes للهرم التنظيمي والأشخاص والممولين | ✅ تم (`Route::resources` مع except(show)، تحقّق فعلياً عبر `php artisan route:list` بدون أخطاء) |
| 11 | Views: قائمة وفورم لكل كيان (Blade + Bootstrap، RTL) | ✅ تم (نمط `_form.blade.php` + `<x-front-layout>`) |
| 12 | Dependent dropdowns (مركز → دائرة → قسم) | ✅ تم بتبسيط مقصود: القائمة صغيرة (10 دوائر فقط)، ففورم الشعبة يعرض كل الدوائر مع اسم المركز كلاحقة بدل تصفية JS حية. Endpoint احتياطي `GET departments/by-center/{center}` (`DepartmentController@byCenter`) موجود لأي تحسين واجهة لاحق |
