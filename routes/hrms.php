<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HRM\DepartmentController;
use App\Http\Controllers\HRM\EmployeeController;
use App\Http\Controllers\HRM\EmployeeEmailController;

Route::middleware(['module:1000000'])->namespace('HRM')->prefix('hrm')->group(function () {

    Route::resource('departments', DepartmentController::class)->except(['edit']);
    Route::post('employees/{employee}/mail', [EmployeeEmailController::class, 'store'])
        ->name('employee-mail.store');
    Route::resource('employees', EmployeeController::class)->except(['edit']);
});
