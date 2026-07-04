# plans/ — دليل التنقّل

هذا المجلد يوثّق تخطيط وتنفيذ نظام raqib. **إن كنت تبحث عن كيف يعمل النظام اليوم، ابدأ من `reference/`** — كل شيء آخر هنا إما سجل تاريخي، نقاط معلّقة، أو نطاق مؤجَّل.

## من أين تبدأ

| تريد معرفة... | اذهب إلى |
|---|---|
| كيف يعمل النظام الآن (as-built) | [`reference/00-overview.md`](reference/00-overview.md) ثم بالترتيب 01→11 |
| نقطة لم تُحسم بعد مع العميل | [`pending-decisions.md`](pending-decisions.md) |
| ميزة مؤجَّلة عن قصد (محضر اجتماع، تقارير...) | [`future-scope.md`](future-scope.md) |
| كيف ولماذا نُفِّذت مرحلة معيّنة تاريخياً | [`phases/`](phases/) |
| التخطيط الأصلي القديم (قبل استقرار القرارات) | [`archive/`](archive/) — **لا تعتمد عليه** |
| اتفاقيات الكود العامة (Controllers/Views/Policies) الملزمة دائماً | `CLAUDE.md` في جذر المشروع |

**عند أي تعارض بين هذه الملفات: الكود الفعلي يفوز أولاً، ثم `reference/`، ثم `CLAUDE.md` للاتفاقيات العامة غير الموثَّقة هنا بالتفصيل.**

## هيكل المجلد

```
plans/
├── README.md                  هذا الملف
│
├── reference/                 ★ المرجع الحي as-built — مقسَّم بالموضوع، 12 ملفاً مرقّماً
│   ├── 00-overview.md              الفكرة، النطاق، المكدس، حالة النظام
│   ├── 01-architecture.md          فصل المصدر عن النشاط، علاقة 1:N، لحظة توليد النشاط
│   ├── 02-data-model.md            كل الجداول والحقول والعلاقات
│   ├── 03-roles-and-org.md         الأدوار الوظيفية + الهرم التنظيمي + الأشخاص
│   ├── 04-permissions.md          abilities + ModelPolicy + role_user
│   ├── 05-visibility-matrix.md     من يرى بيانات من (منسق/مراقب)
│   ├── 06-project-approval-workflow.md  سلسلة اعتماد المشروع
│   ├── 07-monitoring-workflow.md   سلسلة المراقبة الموحّدة
│   ├── 08-reject-and-return.md     الرفض/الإرجاع (return_target)
│   ├── 09-checklist-and-readiness.md  القائمة الديناميكية وحساب الجاهزية
│   ├── 10-constants.md             الثوابت والسلالم
│   └── 11-ui-conventions.md        اتفاقيات الواجهة
│
├── pending-decisions.md       نقاط معلّقة بانتظار قرار العميل، بحالة كل واحدة
├── future-scope.md            مؤجَّل عن قصد: محضر اجتماع، تقرير قسم، تقارير تحليلية
│
├── phases/                    سجل تنفيذ تاريخي (كيف بُني كل شيء بالترتيب الزمني)
│   ├── phase-1-foundation/
│   ├── phase-2-monitoring-activities/
│   ├── phase-3-projects/
│   └── phase-4-future/
│
├── TASKS.md                   فهرس المراحل (حالة إجمالية لكل مرحلة)
├── QA-REPORT.md                آخر جولة تصحيح/اختبار موثَّقة
├── project.xlsx / KPI_v15_meetings-4.xlsx / أسئلة_العميل_نظام_raqib.pdf
│                               ملفات المصدر الأصلية (نظام Excel + أسئلة العميل) — مرجع خام، ليس موثَّقاً بصيغة md
│
└── archive/                   تخطيط تاريخي متجاوَز — لا يُعتمد (انظر archive/README.md)
    ├── README.md
    ├── raqib-verification-prompt.md
    ├── raqib-system-reference.md
    └── v1/
```

## لماذا أُعيد التنظيم؟

كان هناك عدة ملفات "مرجع موحّد" متضاربة (`raqib-system-reference.md`، `v1/raqib-master-reference.md`، `v1/01-context.md`…`04-open-questions.md`) — كل واحد يدّعي إلغاء البقية، وقرارات متأخرة (مثل توحيد الرفض عبر `return_target`) لم تكن موثَّقة في أي منها. `reference/` يحل هذا بملف واحد لكل موضوع حقيقي في الكود، يعكس الحالة الفعلية اليوم، مع فصل واضح بين الحي (`reference/`) والتاريخي (`phases/`, `archive/`) والمعلّق (`pending-decisions.md`) والمؤجَّل (`future-scope.md`).
