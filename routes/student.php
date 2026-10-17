<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Student\StudentController;

Route::redirect('/student-panel.html', '/student');

Route::middleware(['auth', 'cfo', 'cfo:Student'])->group(function () {
    Route::get('/student', [StudentController::class, 'page']);
    Route::get('/api/student', [StudentController::class, 'state']);
    Route::post('/api/student/notifications/viewed', [StudentController::class, 'viewNotifications']);
    Route::post('/api/registrations', [StudentController::class, 'register']);
    Route::post('/api/registrations/{registration}/retry', [StudentController::class, 'retryRegistration']);
    Route::patch('/api/registrations/{registration}', [StudentController::class, 'updateRegistration']);
    Route::post('/api/registrations/{registration}/receipt', [StudentController::class, 'receipt']);
    Route::post('/api/change-requests', [StudentController::class, 'change']);
    Route::patch('/api/change-requests/{change}/cancel', [StudentController::class, 'cancelChange']);
    Route::post('/api/registrations/{registration}/schedule', [StudentController::class, 'chooseSchedule']);
});
