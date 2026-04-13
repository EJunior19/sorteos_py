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

// 🏠 Home → lista de sorteos
Route::get('/', [RaffleController::class, 'index'])->name('home');

// 🏆 Ganadores
Route::get('/winners', [RaffleController::class, 'winners'])->name('winners');

// 🟢 PARTICIPAR (play)
Route::get('/sorteo/{id}/play', [RaffleController::class, 'play'])->name('raffle.play');

// 🔢 ELEGIR NÚMEROS
Route::get('/sorteo/{id}/numeros', [RaffleController::class, 'numbers'])->name('raffle.numbers');

// 🔴 RESULTADOS (show)
Route::get('/sorteo/{id}', [RaffleController::class, 'show'])->name('raffle.show');

// 🛒 RESERVAR NÚMEROS
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

    // Dashboard (admin y colaborador)
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Confirmar pago (admin y colaborador)
    Route::post('/admin/confirmar/{id}', [AdminController::class, 'confirmarPago'])->name('admin.confirmarPago');

    // Solo admin
    Route::middleware('admin.only')->group(function () {

        // Crear sorteo
        Route::get('/admin/create', [AdminController::class, 'create'])->name('admin.create');
        Route::post('/admin/create', [AdminController::class, 'store'])->name('admin.store');

        // Vista ruleta
        Route::get('/admin/roulette/{id}', [AdminController::class, 'vistaSorteo'])->name('admin.roulette');

        // Ejecutar sorteo
        Route::post('/admin/sortear/{id}', [AdminController::class, 'sortear'])->name('admin.sortear');
    });
});