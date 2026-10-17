<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AdminController, AuthController, LiveController, PageController};

Route::get('/', [PageController::class, 'login']);
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::redirect('/admin-panel.html', '/admin');
Route::post('/api/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/api/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/api/logout', [AuthController::class, 'logout']);
Route::post('/api/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:60,1');
Route::get('/api/recovery', [AuthController::class, 'recoveryStatus'])->middleware('throttle:30,1');
Route::post('/api/recovery/verify', [AuthController::class, 'verifyRecovery'])->middleware('throttle:10,1');
Route::post('/api/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
Route::middleware('auth')->group(function () {
    Route::get('/force-password', [PageController::class, 'forcePassword']);
    Route::post('/api/force-password', [AuthController::class, 'forcePassword'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'cfo'])->group(function () {
    Route::get('/api/live/versions', [LiveController::class, 'versions'])->middleware('throttle:120,1');
    Route::put('/api/profile', [AuthController::class, 'profile']);
    Route::get('/api/receipts/{registration}', [PageController::class, 'receipt']);
    Route::get('/certificates/{certificate}', [PageController::class, 'certificate']);
    Route::get('/api/history/{registration}', [PageController::class, 'historyRecord']);
    Route::middleware('cfo:Admin')->group(function () {
        Route::get('/admin/recollection-registrations', [\App\Http\Controllers\RecollectionRegistrationController::class, 'index'])->name('admin.registrations.index');
        Route::get('/admin/recollection-registrations/export', [\App\Http\Controllers\RecollectionRegistrationController::class, 'export'])->name('admin.registrations.export');
        Route::get('/admin/recollection-registrations/{registration}', [\App\Http\Controllers\RecollectionRegistrationController::class, 'show'])->name('admin.registrations.show');
        Route::get('/admin', [PageController::class, 'page']);
        Route::get('/admin/schedules/{id}/edit', [PageController::class, 'scheduleEdit'])->whereNumber('id')->name('admin.schedules.edit');
        Route::get('/api/admin/events', [LiveController::class, 'adminEvents']);
        Route::get('/api/admin/state', [AdminController::class, 'state']);
        Route::get('/api/admin/nav-badges', \App\Http\Controllers\NavBadgeController::class)->middleware('throttle:120,1');
        Route::post('/api/admin/announcements', [AdminController::class, 'announcement']);
        Route::post('/api/admin/announcements/drafts', [AdminController::class, 'saveAnnouncementDraft']);
        Route::put('/api/admin/announcements/{announcement}', [AdminController::class, 'saveAnnouncementDraft'])->whereNumber('announcement');
        Route::post('/api/admin/announcements/{announcement}/publish', [AdminController::class, 'publishAnnouncementDraft'])->whereNumber('announcement');
        Route::delete('/api/admin/announcements/{announcement}', [AdminController::class, 'deleteAnnouncement'])->whereNumber('announcement');
        Route::post('/api/admin/certificate-template/preview', [\App\Http\Controllers\CertificateTemplateController::class, 'preview']);
        Route::patch('/api/admin/users/{user}', [AdminController::class, 'user']);
        Route::patch('/api/admin/users/{user}/review-admin', [AdminController::class, 'reviewAdmin']);
        Route::post('/api/admin/users/{user}/password-reset', [AdminController::class, 'resetStudentPassword'])->middleware('throttle:10,1');
        Route::post('/api/admin/schedules', [AdminController::class, 'schedule']);
        Route::put('/api/admin/schedules/{schedule}', [AdminController::class, 'schedule']);
        Route::patch('/api/admin/schedules/{schedule}/status', [AdminController::class, 'scheduleStatus']);
        Route::delete('/api/admin/schedules/{schedule}', [AdminController::class, 'deleteSchedule']);
        Route::patch('/api/admin/registrations/{registration}', [AdminController::class, 'registration']);
        Route::post('/api/admin/registrations/{registration}/schedule', [\App\Http\Controllers\BatchWorkflowController::class, 'assign']);
        Route::post('/api/admin/registrations/{registration}/attendance', [\App\Http\Controllers\BatchWorkflowController::class, 'attendance']);
        Route::post('/api/admin/schedules/{schedule}/archive', [\App\Http\Controllers\BatchWorkflowController::class, 'archive']);
        Route::patch('/api/admin/change-requests/{change}', [AdminController::class, 'change']);
        Route::post('/api/admin/registrations/{registration}/certificate', [AdminController::class, 'certificate']);
        Route::post('/api/admin/settings/{key}', [AdminController::class, 'settings']);
        Route::post('/api/admin/academic-options', [AdminController::class, 'academic']);
        Route::patch('/api/admin/academic-options/{id}', [AdminController::class, 'updateAcademic']);
        Route::delete('/api/admin/academic-options/{id}', [AdminController::class, 'deleteAcademic']);
        Route::post('/api/admin/course-year-sections', [AdminController::class, 'courseYearSection']);
        Route::delete('/api/admin/course-year-sections/{id}', [AdminController::class, 'deleteCourseYearSection']);
        Route::patch('/api/admin/password-resets/{reset}', [AdminController::class, 'recovery']);
    });
});
