# 00 — نظرة عامة

> جزء من `plans/reference/` — المرجع الحي (as-built) لنظام raqib. عند التعارض مع أي ملف آخر في `plans/`: **الكود الفعلي يفوز أولاً، ثم هذا المجلد**. التفصيل الكامل والمُلزم دائماً هو `CLAUDE.md` في جذر المشروع؛ هذا المجلد شرح موسّع مقسَّم بالموضوع لا يكرر ما فيه.

## الفكرة

**raqib-monitoring** نظام Laravel للرقابة والمتابعة (M&E/KPI) لجمعية خيرية في غزة (جمعية الصلاح الإسلامية)، محوَّل من نظام Excel أصلي (12 ورقة، معادلات مترابطة) إلى تطبيق ويب. مراقب ميداني يزور أقسام/مشاريع الجمعية، يسجّل نشاطاً رقابياً، والنظام يحسب لكل نشاط درجة أداء (KPI) بمعادلة مرجّحة ثابتة.

## المكدس

Laravel 11، PHP 8.2+، Fortify للمصادقة، Yajra DataTables، Maatwebsite Excel، mPDF. عربي RTL، Bootstrap 5، قالب Vuexy/horizontal.

المشروع مبني فوق **Laravel starter kit** كان أصلاً لنظام توزيع مساعدات — كثير من ذلك باقٍ في الكود (انظر قسم Legacy في `CLAUDE.md`) لكن القائمة الفعلية للمستخدم (`asideH.blade.php`) تعرض وحدات raqib فقط.

## أولوية التنفيذ المعتمَدة (كما نُفِّذت فعلياً)

1. **الأساس**: قاعدة البيانات، الثوابت، الهرم التنظيمي، الأشخاص، الصلاحيات — انظر [03-roles-and-org](03-roles-and-org.md)، [04-permissions](04-permissions.md)، [10-constants](10-constants.md).
2. **النشاطات الرقابية** (`monitoring_activities`) — الكيان المركزي المُراقَب — انظر [01-architecture](01-architecture.md)، [07-monitoring-workflow](07-monitoring-workflow.md).
3. **المشاريع** (`projects`) — الجدول، الفورم، دورة الاعتماد والمراقبة، قائمة التحقق — انظر [06-project-approval-workflow](06-project-approval-workflow.md)، [09-checklist-and-readiness](09-checklist-and-readiness.md).

**مؤجَّل بالكامل (لا تخطيط تفصيلي):** محضر الاجتماع، تقرير القسم، كل التقارير التحليلية المشتقة، استيراد بيانات Excel القديمة (مُلغى نهائياً من النطاق). التفاصيل في [../future-scope.md](../future-scope.md).

## حالة النظام الحالية (as-built)

الوحدات الثلاث أعلاه **منجزة ومختبرة** (`tests/Feature/ProjectsSmokeTest.php` يغطي السلسلة الكاملة). آخر جولة تصحيح كبرى (2026-07-04، موثّقة في [../../QA-REPORT.md](../../QA-REPORT.md)):
- إزالة `EMPLOYEE_ALLOWED_ABILITIES` من `ModelPolicy` (الاعتماد الكامل على `role_user`/abilities).
- توحيد الرفض/الإرجاع عبر حقل `return_target` (migration `2026_07_04_120000_add_return_target_to_projects_table`).
- عزل رؤية المنسق/المراقب عبر `Project::showsCoordinatorDataTo()` / `showsMonitorDataTo()` / `isAssignedMonitor()`.
- صفحة عرض مستقلة للنشاط الرقابي (`monitoring-activities/{id}`).
- لوحة رئيسية (`HomeController`) مخصّصة لكل دور وظيفي.

## خريطة الوحدات

| الوحدة | Model | Controller | Views |
|--------|-------|------------|-------|
| المراكز/الدوائر/الأقسام | `Center`, `Department`, `Section` | `CenterController` وما شابه | `dashboard/centers/` وما شابه |
| الأشخاص | `Person` | `PersonController` | `dashboard/people/` |
| الممولون | `Funder` | `FunderController` | `dashboard/funders/` |
| ثوابت النظام | `Constant` | `ConstantController` | `dashboard/pages/constants` |
| المستخدمون | `User` | `UserController` | `dashboard/users/` |
| النشاطات الرقابية | `MonitoringActivity` | `MonitoringActivityController` | `dashboard/monitoring-activities/` |
| المشاريع | `Project` | `ProjectController` | `dashboard/projects/` |
| إدارة قائمة التحقق | — (`ChecklistGroup`/`ChecklistItem`) | `ChecklistAdminController` | `dashboard/checklist-admin/` |
| سجل العمليات | `ActivityLog` | `ActivityLogController` | `dashboard/pages/logs` |

جدول أوسع بالمسارات وأنماط الجداول موجود في `CLAUDE.md`.

## فهرس ملفات reference/

| # | الملف | الموضوع |
|---|-------|---------|
| 00 | overview.md | هذا الملف |
| 01 | [architecture.md](01-architecture.md) | فصل المصدر عن النشاط، علاقة 1:N، لحظة التوليد |
| 02 | [data-model.md](02-data-model.md) | كل الجداول والحقول والعلاقات |
| 03 | [roles-and-org.md](03-roles-and-org.md) | الأدوار الوظيفية، الهرم، الأشخاص |
| 04 | [permissions.md](04-permissions.md) | abilities + ModelPolicy + role_user |
| 05 | [visibility-matrix.md](05-visibility-matrix.md) | من يرى بيانات من |
| 06 | [project-approval-workflow.md](06-project-approval-workflow.md) | سلسلة اعتماد المشروع |
| 07 | [monitoring-workflow.md](07-monitoring-workflow.md) | سلسلة المراقبة الموحّدة |
| 08 | [reject-and-return.md](08-reject-and-return.md) | الرفض/الإرجاع |
| 09 | [checklist-and-readiness.md](09-checklist-and-readiness.md) | القائمة الديناميكية ونسب الجاهزية |
| 10 | [constants.md](10-constants.md) | الثوابت والسلالم |
| 11 | [ui-conventions.md](11-ui-conventions.md) | اتفاقيات الواجهة |

انظر أيضاً [../pending-decisions.md](../pending-decisions.md) (نقاط معلّقة) و[../future-scope.md](../future-scope.md) (مؤجَّل) و[../phases/](../phases/) (سجل تنفيذ تاريخي) و[../archive/](../archive/) (تخطيط قديم متجاوَز).
