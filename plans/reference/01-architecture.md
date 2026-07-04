# 01 — المعمارية: فصل المصدر عن النشاط

> راجع [00-overview](00-overview.md) للسياق العام.

## القرار الجوهري

الفهم الأول (تحليل Excel وحده) كان: كل شيء (مشاريع، محاضر، أنشطة خارجية) يُحشر في جدول واحد بنفس الأعمدة — يطابق Excel حرفياً لكنه تصميم غير صحيح لتطبيق ويب. **القرار النهائي المطبَّق:**

```
مصادر (كل واحد جدول مستقل بحقوله الخاصة):
  projects (مشاريع)      ── تحتاج دخول/تعديل متكرر، تقرير غني خاص بها — منجز
  meetings (محاضر)       ── مؤجَّل بالكامل (انظر ../future-scope.md)
  departments (تقرير قسم) ── مؤجَّل بالكامل

          ↓ كل مصدر "يولّد" نشاطاً واحداً أو أكثر ↓

  monitoring_activities ← الكيان المركزي المُراقَب
     • يحمل الحقول المشتركة لأي نشاط (هرم تنظيمي، مراقب، تقييم KPI...)
     • هو من يمرّ بمراحل المراقبة — ليس المصدر
     • مربوط بمصدره عبر source_type + source_id
```

**نشاط خارجي مباشر** (مثال: متابعة سيارة، متابعة مبنى): لا يحتاج جدول مصدر منفصل — يُدخل مباشرة في `monitoring_activities` بـ `source_type='external'`, `source_id=null`، ويدخل مباشرة سلسلة المراقبة ([07-monitoring-workflow](07-monitoring-workflow.md)) بدون سلسلة اعتماد مشروع.

## علاقة المشروع بالنشاط: 1:N

القرار الأقدم كان 1:1 (كل مشروع = نشاط واحد يتحدّث). **صُحِّح لاحقاً إلى 1:N**:

- **نشاط أساسي (`activity_role='primary'`)**: واحد إلزامي لكل مشروع، يتولّد **تلقائياً**.
- **أنشطة تابعة (`activity_role='secondary'`)**: عدد غير محدود، تُنشأ **يدوياً** لاحقاً. مثال مذكور من صاحب المشروع: تحويل ملاحظة ميدانية لنشاط مستقل بضغطة زر. **البنية جاهزة لهذا** (`Project::secondaryMonitoringActivities()`، عمود `activity_role`)، لكن **واجهة/منطق التحويل الفعلي غير مبني** — لم يُطلب بعد.

الأثر البنيوي: `monitoring_activities.source_id` **بدون unique constraint** (عدة أنشطة تتشارك نفس المشروع)، ويُميَّز بينها بـ `activity_role`. `projects.primary_monitoring_activity_id` (FK nullable) يُشير فقط للنشاط الأساسي — الأنشطة التابعة تُستعلَم عبر `monitoring_activities::where('source_type','project')->where('source_id', $project->id)`.

## لحظة توليد النشاط الأساسي

نقطة حرجة صُحِّحت أثناء التخطيط: **ليس** عند وصول المشروع لمدير الرقابة العامة، بل **عند لحظة تعيين مدير الرقابة العامة لمراقب محدد** على المشروع تحديداً (`assign-monitor`، انظر [06-project-approval-workflow](06-project-approval-workflow.md)). الترتيب: مدير الرقابة يستلم ← يحدد طريقة/مرحلة المراقبة ← **يعيّن مراقباً ← عند هذا التعيين تحديداً يتولّد النشاط**. من هذه اللحظة فصاعداً لا علاقة للمنسق أو مدير الدائرة بالنشاط.

عند التوليد (`Project::syncMonitoringWorkflowState()` وما حولها في `ProjectController@assignMonitor`):
1. سجل جديد في `monitoring_activities`: `source_type='project'`, `source_id=$project->id`, `activity_role='primary'`, `reference_code` تلقائي، `center_id/department_id/section_id` مستنسخة من المشروع، `monitor_person_id`، `workflow_status='in_progress'`.
2. `projects.primary_monitoring_activity_id` يُحدَّث بالمعرّف الجديد.
3. `projects.workflow_status` ينتقل إلى `monitoring_in_progress`.

## قواعد صارمة مترتبة على هذه المعمارية

- كل حقل "شخص" = FK إلى `people`، أبداً نص حر (انظر [03-roles-and-org](03-roles-and-org.md)).
- النشاط يُسمح أن يُحفظ **ناقصاً** دائماً — حقل التحقق للعرض فقط، لا يمنع الحفظ.
- حقول التقييم الأربعة تُخزَّن كأرقام دائماً؛ التسمية النصية مشتقة للعرض فقط ([10-constants](10-constants.md)).
- الفورمات = أقسام منظمة عادية، **ليست** أسلوب Wizard.
- لا خلط بين `activity_logs` (audit trail عام للمستخدمين، موجود من الـ starter kit) وجدول `monitoring_activities` — لا علاقة بينهما إطلاقاً.
