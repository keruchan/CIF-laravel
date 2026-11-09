<?php

use App\Http\Controllers\Recruitment\AccessController;
use App\Http\Controllers\Recruitment\DepartmentController;
use App\Http\Controllers\Recruitment\EmailController;
use App\Http\Controllers\Recruitment\MrfController;
use App\Http\Controllers\Recruitment\MrfImportController;
use App\Http\Controllers\Recruitment\MrfRequestorsController;
use App\Http\Controllers\Recruitment\PortalController;
use App\Http\Controllers\Recruitment\ReportsController;
use App\Http\Controllers\Recruitment\SuperadminController;
use App\Http\Middleware\RecruitmentSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(RecruitmentSession::class)->group(function () {
    foreach (['/', '/index.php', '/index.html'] as $entry) {
        Route::get($entry, fn () => new RedirectResponse('/pages/candidate/'));
    }
    foreach (['candidate', 'admin', 'superadmin', 'portal'] as $portal) {
        Route::get('/pages/'.$portal.'/', [PortalController::class, $portal])->name('portal.'.$portal);
        Route::get('/pages/'.$portal.'/index.php', [PortalController::class, $portal]);
        Route::get('/'.$portal, fn () => new RedirectResponse('/pages/'.$portal.'/'));
    }
    Route::get('/pages/admin/record.php', [PortalController::class, 'candidateRecord']);
    Route::get('/pages/admin/mrf.php', [PortalController::class, 'adminMrf']);
    foreach (['', 'index.php', 'mrf.php', 'mrf-form.php'] as $page) {
        Route::get('/pages/department/'.$page, function (Request $request) use ($page) {
            return new RedirectResponse('/pages/mrf-requestor/'.($page === 'index.php' ? '' : $page).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 302);
        });
    }
    Route::get('/department', fn () => new RedirectResponse('/pages/mrf-requestor/'));
    Route::get('/pages/mrf-requestor/', [PortalController::class, 'department']);
    Route::get('/pages/mrf-requestor/index.php', [PortalController::class, 'department']);
    Route::get('/pages/mrf-requestor/mrf.php', [PortalController::class, 'departmentMrf']);
    Route::get('/pages/mrf-requestor/mrf-form.php', [PortalController::class, 'form']);
    Route::get('/mrf-requestor', fn () => new RedirectResponse('/pages/mrf-requestor/'));

    // Keep bookmarked URLs and the existing frontend API contracts.
    foreach ([
        'access' => AccessController::class,
        'department' => DepartmentController::class,
        'mrf-requestor' => DepartmentController::class,
        'superadmin' => SuperadminController::class,
        'mrf' => MrfController::class,
        'mrf-import' => MrfImportController::class,
        'mrf-requestors' => MrfRequestorsController::class,
        'email' => EmailController::class,
        'reports' => ReportsController::class,
    ] as $endpoint => $controller) {
        Route::match(['GET', 'POST'], '/ajax/'.$endpoint.'.php', $controller)->block(30, 30);
    }
});
