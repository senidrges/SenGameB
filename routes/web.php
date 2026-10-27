<?php

use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Privacy policy linked from the Play Store listing and the app.
Route::view('confidentialite', 'privacy', ['contactEmail' => 'georgesseni1@gmail.com'])->name('privacy');

// Opened from the password reset email sent to mobile users.
Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:auth')->name('password.store');
