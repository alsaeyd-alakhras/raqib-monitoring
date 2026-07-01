<?php


// dashboard routes

use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\AidDistributionController;
use App\Http\Controllers\Dashboard\CenterController;
use App\Http\Controllers\Dashboard\ConstantController;
use App\Http\Controllers\Dashboard\CurrencyController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\FunderController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\PersonController;
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

    Route::resources([
        'centers' => CenterController::class,
        'departments' => DepartmentController::class,
        'sections' => SectionController::class,
        'people' => PersonController::class,
        'funders' => FunderController::class,
    ], ['except' => ['show']]);

    /* ********************************************************** */
});
