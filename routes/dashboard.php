<?php


// dashboard routes

use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\AidDistributionController;
use App\Http\Controllers\Dashboard\CenterController;
use App\Http\Controllers\Dashboard\ChecklistAdminController;
use App\Http\Controllers\Dashboard\ConstantController;
use App\Http\Controllers\Dashboard\CurrencyController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\FunderController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\MonitoringActivityController;
use App\Http\Controllers\Dashboard\PersonController;
use App\Http\Controllers\Dashboard\ProjectController;
use App\Http\Controllers\Dashboard\SectionController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\ReportController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '',
    'middleware' => ['check.cookie'],
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
    Route::get('profile/settings',[UserController::class,'settings'])->name('profile.settings');

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
    Route::get('departments/by-center/{center}', [DepartmentController::class, 'byCenter'])->name('departments.by-center');
    Route::get('sections/by-department/{department}', [SectionController::class, 'byDepartment'])->name('sections.by-department');

    Route::resources([
        'centers' => CenterController::class,
        'departments' => DepartmentController::class,
        'sections' => SectionController::class,
        'people' => PersonController::class,
        'funders' => FunderController::class,
    ], ['except' => ['show']]);

    // Monitoring activities ************************
    Route::resources([
        'monitoring-activities' => MonitoringActivityController::class,
    ]);

    Route::post('monitoring-activities/{monitoring_activity}/confirm-passage', [MonitoringActivityController::class, 'confirmPassage'])
        ->name('monitoring-activities.confirm-passage');
    Route::post('monitoring-activities/{monitoring_activity}/reject', [MonitoringActivityController::class, 'reject'])
        ->name('monitoring-activities.reject');

    // Projects ************************
    Route::get('projects/check-project-number', [ProjectController::class, 'checkProjectNumber'])
        ->name('projects.check-project-number');

    Route::resources([
        'projects' => ProjectController::class,
    ]);

    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::post('submit-to-coordinator', [ProjectController::class, 'submitToCoordinator'])->name('submit-to-coordinator');
        Route::post('fill-coordinator', [ProjectController::class, 'fillCoordinator'])->name('fill-coordinator');
        Route::post('submit-to-dept-manager', [ProjectController::class, 'submitToDeptManager'])->name('submit-to-dept-manager');
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
