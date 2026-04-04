<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RaffleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservationController;

/*
|--------------------------------------------------------------------------
| 🌐 RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

// Home → lista de sorteos
Route::get('/', [RaffleController::class, 'index'])->name('home');

// Ver sorteo
Route::get('/sorteo/{id}', [RaffleController::class, 'show'])->name('raffle.show');

// Reservar números
Route::post('/reservar', [ReservationController::class, 'reservar'])->name('reservar');


/*
|--------------------------------------------------------------------------
| 🔐 AUTENTICACIÓN ADMIN
|--------------------------------------------------------------------------
*/

// Login
Route::get('/admin/login', [AuthController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'doLogin'])->name('admin.doLogin');

// Logout
Route::get('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');


/*
|--------------------------------------------------------------------------
| 🧑‍💼 ADMIN (PROTEGIDO)
|--------------------------------------------------------------------------
*/

Route::middleware('admin.auth')->group(function () {

    // Dashboard
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Crear sorteo
    Route::get('/admin/create', [AdminController::class, 'create'])->name('admin.create');

    // Guardar sorteo
    Route::post('/admin/create', [AdminController::class, 'store'])->name('admin.store');

    // Confirmar pago
    Route::post('/admin/confirmar/{id}', [AdminController::class, 'confirmarPago'])->name('admin.confirmarPago');

    // 🎯 SORTEAR GANADOR (🔥 NUEVO)
    Route::post('/admin/sortear/{id}', [AdminController::class, 'sortear'])->name('admin.sortear');

});