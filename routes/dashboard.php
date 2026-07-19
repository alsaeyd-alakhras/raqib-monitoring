<?php


// dashboard routes

use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\AidDistributionController;
use App\Http\Controllers\Dashboard\CenterController;
use App\Http\Controllers\Dashboard\ChecklistAdminController;
use App\Http\Controllers\Dashboard\ConstantController;
use App\Http\Controllers\Dashboard\CurrencyController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\DirectoryController;
use App\Http\Controllers\Dashboard\FunderController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\MonitoringActivityController;
use App\Http\Controllers\Dashboard\OrganizationalStructureController;
use App\Http\Controllers\Dashboard\PersonController;
use App\Http\Controllers\Dashboard\ProjectController;
use App\Http\Controllers\Dashboard\SectionController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\ReportController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '',
    'middleware' => ['check.cookie', 'ensure.phone'],
    'as' => 'dashboard.'
], function () {
    /* ********************************************************** */

    // Dashboard ************************
    Route::get('/', [HomeController::class,'index'])->name('home');
    Route::post('dashboard/refresh-cache', [HomeController::class, 'refreshDashboardCache'])->name('home.refresh-cache');

    // Logs ************************
    Route::get('logs',[ActivityLogController::class,'index'])->name('logs.index');
    Route::get('getLogs',[ActivityLogController::class,'getLogs'])->name('logs.getLogs');

    // users ************************
    Route::get('profile/complete-phone', [UserController::class, 'completePhone'])->name('profile.complete-phone');
    Route::put('profile/complete-phone', [UserController::class, 'storeCompletePhone'])->name('profile.complete-phone.store');
    Route::get('profile/settings', [UserController::class, 'settings'])->name('profile.settings');
    Route::put('profile/settings', [UserController::class, 'updateProfile'])->name('profile.update');

    // Reports ************************
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/tradersReve', [ReportController::class, 'tradersReve'])->name('reports.tradersReve');
    Route::get('reports/brokersReve', [ReportController::class, 'brokersReve'])->name('reports.brokersReve');
    Route::get('reports/broker-details', [ReportController::class, 'brokerDetails'])->name('reports.brokerDetails');
    /* ********************************************************** */

    // Merchants ************************    
    Route::get('aid-distributions-filters/{cloumn}', [AidDistributionController::class, 'getFilterOptions'])->name('aid-distributions.filters');
    /* ********************************************************** */

    // Resources

    Route::resource('constants', ConstantController::class)->only(['index','store','destroy']);
    Route::resource('currencies', CurrencyController::class)->except(['show','edit','create']);

    Route::resources([
        'users' => UserController::class,
    ]);

    // Foundation — organizational hierarchy + people + funders ************************
    Route::get('org-structure', [OrganizationalStructureController::class, 'index'])->name('org-structure.index');
    Route::get('org-structure/tree', [OrganizationalStructureController::class, 'tree'])->name('org-structure.tree');
    Route::get('org-structure/node/{type}/{id}', [OrganizationalStructureController::class, 'node'])->name('org-structure.node');
    Route::post('org-structure', [OrganizationalStructureController::class, 'store'])->name('org-structure.store');
    Route::put('org-structure/{type}/{id}', [OrganizationalStructureController::class, 'update'])->name('org-structure.update');
    Route::delete('org-structure/{type}/{id}', [OrganizationalStructureController::class, 'destroy'])->name('org-structure.destroy');

    Route::get('directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('directory/data', [DirectoryController::class, 'data'])->name('directory.data');
    Route::get('directory-filters/{column}', [DirectoryController::class, 'getFilterOptions'])->name('directory.filters');
    Route::get('directory/role-abilities/{role?}', [DirectoryController::class, 'roleAbilities'])->name('directory.role-abilities');
    Route::get('directory/create', [DirectoryController::class, 'create'])->name('directory.create');
    Route::post('directory', [DirectoryController::class, 'store'])->name('directory.store');
    Route::get('directory/{record}/edit', [DirectoryController::class, 'edit'])->name('directory.edit');
    Route::put('directory/{record}', [DirectoryController::class, 'update'])->name('directory.update');
    Route::delete('directory/{record}', [DirectoryController::class, 'destroy'])->name('directory.destroy');

    Route::get('departments/by-center/{center}', [DepartmentController::class, 'byCenter'])->name('departments.by-center');
    Route::get('sections/by-department/{department}', [SectionController::class, 'byDepartment'])->name('sections.by-department');
    Route::get('sections/for-project/{department}', [SectionController::class, 'forProject'])->name('sections.for-project');

    Route::resources([
        'centers' => CenterController::class,
        'departments' => DepartmentController::class,
        'sections' => SectionController::class,
        'people' => PersonController::class,
        'funders' => FunderController::class,
    ], ['except' => ['show']]);

    // Monitoring activities ************************
    Route::get('monitoring-activities-filters/{column}', [MonitoringActivityController::class, 'getFilterOptions'])
        ->name('monitoring-activities.filters');
    Route::get('monitoring-activities/check-reference-code', [MonitoringActivityController::class, 'checkReferenceCode'])
        ->name('monitoring-activities.check-reference-code');

    Route::resources([
        'monitoring-activities' => MonitoringActivityController::class,
    ]);

    Route::post('monitoring-activities/{monitoring_activity}/confirm-passage', [MonitoringActivityController::class, 'confirmPassage'])
        ->name('monitoring-activities.confirm-passage');
    Route::post('monitoring-activities/{monitoring_activity}/submit-to-director', [MonitoringActivityController::class, 'submitToDirector'])
        ->name('monitoring-activities.submit-to-director');
    Route::post('monitoring-activities/{monitoring_activity}/reject', [MonitoringActivityController::class, 'reject'])
        ->name('monitoring-activities.reject');
    Route::get('monitoring-activities/{monitoring_activity}/export-pdf', [MonitoringActivityController::class, 'exportPdf'])
        ->name('monitoring-activities.export-pdf');
    Route::get('monitoring-activities/{monitoring_activity}/export-excel', [MonitoringActivityController::class, 'exportExcel'])
        ->name('monitoring-activities.export-excel');

    // Projects ************************
    Route::get('projects-filters/{column}', [ProjectController::class, 'getFilterOptions'])
        ->name('projects.filters');
    Route::get('projects/check-project-number', [ProjectController::class, 'checkProjectNumber'])
        ->name('projects.check-project-number');

    Route::resources([
        'projects' => ProjectController::class,
    ]);

    Route::get('projects/{project}/export-pdf', [ProjectController::class, 'exportPdf'])
        ->name('projects.export-pdf');

    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::post('submit-to-secretariat', [ProjectController::class, 'submitToSecretariat'])->name('submit-to-secretariat');
        Route::post('fill-secretariat', [ProjectController::class, 'fillSecretariat'])->name('fill-secretariat');
        Route::post('submit-to-coordinator', [ProjectController::class, 'submitToCoordinator'])->name('submit-to-coordinator');
        Route::post('fill-coordinator', [ProjectController::class, 'fillCoordinator'])->name('fill-coordinator');
        Route::post('fill-closure-docs', [ProjectController::class, 'fillClosureDocs'])->name('fill-closure-docs');
        Route::post('delete-checklist-attachment', [ProjectController::class, 'deleteChecklistAttachment'])->name('delete-checklist-attachment');
        Route::post('submit-to-project-manager', [ProjectController::class, 'submitToProjectManager'])->name('submit-to-project-manager');
        Route::post('submit-to-section-manager', [ProjectController::class, 'submitToSectionManager'])->name('submit-to-section-manager');
        Route::post('approve-section', [ProjectController::class, 'approveSection'])->name('approve-section');
        Route::post('approve-department', [ProjectController::class, 'approveDepartment'])->name('approve-department');
        Route::post('set-monitoring-info', [ProjectController::class, 'setMonitoringInfo'])->name('set-monitoring-info');
        Route::post('assign-monitor', [ProjectController::class, 'assignMonitor'])->name('assign-monitor');
        Route::get('monitor-work', [ProjectController::class, 'monitorWork'])->name('monitor-work');
        Route::post('fill-monitor', [ProjectController::class, 'fillMonitor'])->name('fill-monitor');
        Route::post('confirm-monitoring', [ProjectController::class, 'confirmMonitoring'])->name('confirm-monitoring');
        Route::post('confirm-passage', [ProjectController::class, 'confirmPassage'])->name('confirm-passage');
        Route::post('reject', [ProjectController::class, 'reject'])->name('reject');
        Route::post('reroute', [ProjectController::class, 'reroute'])->name('reroute');
    });

    // Checklist admin ************************
    Route::prefix('checklist-admin')->name('checklist-admin.')->group(function () {
        Route::get('/', [ChecklistAdminController::class, 'index'])->name('index');
        Route::post('groups', [ChecklistAdminController::class, 'storeGroup'])->name('groups.store');
        Route::put('groups/{group}', [ChecklistAdminController::class, 'updateGroup'])->name('groups.update');
        Route::post('groups/{group}/toggle', [ChecklistAdminController::class, 'toggleGroup'])->name('groups.toggle');
        Route::post('groups/{group}/move', [ChecklistAdminController::class, 'moveGroup'])->name('groups.move');
        Route::post('items', [ChecklistAdminController::class, 'storeItem'])->name('items.store');
        Route::put('items/{item}', [ChecklistAdminController::class, 'updateItem'])->name('items.update');
        Route::post('items/{item}/toggle', [ChecklistAdminController::class, 'toggleItem'])->name('items.toggle');
        Route::post('items/{item}/move', [ChecklistAdminController::class, 'moveItem'])->name('items.move');
    });

    /* ********************************************************** */
});
