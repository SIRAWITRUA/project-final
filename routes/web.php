<?php

use App\Http\Controllers\DriverTripController;
use App\Http\Controllers\UserTripController;
use App\Http\Controllers\Auth\UserLoginController;
use App\Http\Controllers\Auth\EmployeeLoginController;
use App\Http\Controllers\UserReservationController;
use Illuminate\Support\Facades\Route;


Route::middleware('user.auth')->group(function () {
    Route::get('/', [UserTripController::class, 'searchTripListPage'])->name('reservation.search-trip-list');
    // Reservation - Web only
    Route::get('/reservation/create', [UserReservationController::class, 'createForm'])->name('reservation.create');
    Route::post('/reservation', [UserReservationController::class, 'store'])->name('reservation.store');
    Route::post('/reservation/cancel', [UserReservationController::class, 'cancel'])->name('reservation.cancel');
    Route::get('/my/upcoming', [UserReservationController::class, 'myUpcoming'])->name('reservation.upcoming');
    Route::get('/my/history', [UserReservationController::class, 'myHistory'])->name('reservation.history');
});






Route::middleware('user.guest')->group(function () {
    Route::get('/login', [UserLoginController::class, 'loginPage'])->name('auth.user-login');
    Route::post('/login', [UserLoginController::class, 'login'])->name('auth.user-login.post');
});
Route::post('/logout', [UserLoginController::class, 'logout'])
    ->middleware('user.auth')
    ->name('auth.user-logout');

// Employee Auth (Driver)
Route::middleware('employee.guest')->group(function () {
    Route::get('/driver/login', [EmployeeLoginController::class, 'loginPage'])->name('auth.employee-login');
    Route::post('/driver/login', [EmployeeLoginController::class, 'login'])->name('auth.employee-login.post');
});
Route::post('/driver/logout', [EmployeeLoginController::class, 'logout'])
    ->middleware('employee.auth')
    ->name('auth.employee-logout');

// Driver Area (requires employee auth + driver position)
Route::middleware('employee.auth')->group(function () {
    Route::get('/driver', [DriverTripController::class, 'tripListPage'])->name('driver.trip-list');
});
