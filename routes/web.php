<?php

use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Privacy policy linked from the Play Store listing and the app.
Route::view('confidentialite', 'privacy', ['contactEmail' => 'georgesseni1@gmail.com'])->name('privacy');

// Account deletion instructions required by the Play Store (Data safety form).
Route::view('suppression-compte', 'account-deletion', ['contactEmail' => 'georgesseni1@gmail.com'])->name('account-deletion');

// Child safety standards required by the Play Store for social apps.
Route::view('securite-enfants', 'child-safety', ['contactEmail' => 'georgesseni1@gmail.com'])->name('child-safety');

// Opened from the password reset email sent to mobile users.
Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:auth')->name('password.store');
