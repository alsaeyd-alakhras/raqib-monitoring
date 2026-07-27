---
name: aid-registry-subagents
description: >-
  Guides when to use subagents (explore, generalPurpose, shell) vs direct execution
  in the aid-registry Laravel project. Use when the user asks about project structure,
  needs broad exploration, git/shell operations, or multi-step tasks spanning many files.
---

# Aid Registry – Subagent Usage Guide

## When to Use Subagents vs Direct Agent

| Scenario | Use Subagent | Use Direct Agent |
|----------|--------------|------------------|
| استكشاف بنية المشروع، API، routes | ✅ explore | — |
| بحث عن كود في عدة ملفات/مجلدات | ✅ explore | — |
| عمليات git، تشغيل أوامر shell | ✅ shell | — |
| مهام متعددة الخطوات عبر ملفات كثيرة | ✅ generalPurpose | — |
| تعديل ملف واحد معروف | — | ✅ مباشرة |
| إجابة سؤال محدد عن كود معين | — | ✅ مباشرة |
| إصلاح خطأ في ملف مفتوح | — | ✅ مباشرة |

## Subagent Types & When to Use

### explore
- **متى**: استكشاف بنية المشروع، البحث عن patterns، فهم الـ API والـ routes
- **Thoroughness**: `quick` (سريع)، `medium` (متوسط)، `very thorough` (شامل)
- **مثال**: "استكشف مشروع aid-registry، اشرح بنية الـ API والـ routes"

### generalPurpose
- **متى**: مهام بحث وتنفيذ متعددة الخطوات، أسئلة معقدة تحتاج استكشاف ثم تنفيذ
- **مثال**: "ابحث عن جميع استخدامات AidDistribution في المشروع واقترح تحسينات"

### shell
- **متى**: عمليات git، تشغيل أوامر artisan، npm/composer، اختبارات
- **مثال**: "نفّذ php artisan migrate وأخبرني بالنتيجة"

## Task → Subagent Mapping Table

| المهمة | Subagent Type | Prompt المقترح | ملاحظات |
|--------|---------------|----------------|----------|
| استكشاف بنية المشروع | explore | "Explore aid-registry project structure. Document: routes, controllers, models, main entities (offices, institutions, projects, aid-items, aid-distributions). Return structured summary." | thoroughness: medium |
| فهم الـ API والـ routes | explore | "List all API routes and dashboard routes in aid-registry. Include: method, path, controller, purpose." | thoroughness: quick |
| البحث عن استخدامات model/class | explore | "Search aid-registry for all usages of [ModelName] across controllers, views, and services." | thoroughness: medium |
| تحليل علاقات الـ models | explore | "Analyze aid-registry models: list relationships (belongsTo, hasMany) for User, Office, Institution, Project, AidItem, AidDistribution, Family." | thoroughness: medium |
| استكشاف Excel/PDF/DataTables | explore | "Find how Excel import, PDF export, and DataTables are used in aid-registry. List relevant classes and flows." | thoroughness: medium |
| تنفيذ أوامر artisan | shell | "Run: php artisan [command] in aid-registry. Report output and any errors." | — |
| عمليات git (status, diff, commit) | shell | "Run git status / git diff / git add / git commit in aid-registry. [specific instructions]." | — |
| تشغيل الاختبارات | shell | "Run php artisan test (or phpunit) in aid-registry. Summarize results." | — |
| تثبيت/تحديث packages | shell | "Run composer install / composer update [package] in aid-registry." | — |
| مهام متعددة الخطوات | generalPurpose | "In aid-registry: 1) Find ReportController logic. 2) Identify legacy Allocation/Executive references. 3) Suggest migration path." | — |
| مراجعة أمنية/جودة | generalPurpose | "Review aid-registry for: SQL injection risks, XSS, auth bypass. Focus on controllers and policies." | — |

## Project Context (aid-registry)

- **Stack**: Laravel 11, PHP 8.2, Fortify, Excel (Maatwebsite), DataTables (Yajra), PDF (laravel-mpdf)
- **Main entities**: User, Office, Institution, Project, AidItem, AidDistribution, Family, ProjectOfficeAllocation
- **Routes**: `web.php` → `dashboard.php`; no separate `api.php`
- **Key paths**: `app/Http/Controllers/Dashboard/`, `app/Models/`, `routes/dashboard.php`

## Ready-to-Copy Prompts

See [prompts.md](prompts.md) for Arabic and English prompts you can paste directly.
