<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductColorGroupController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SizeChartController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::redirect('/dashboard', '/admin');
Route::get('/admin', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::resource('color-groups', ProductColorGroupController::class)
            ->parameters(['color-groups' => 'color_group'])
            ->except(['show']);
        Route::resource('size-charts', SizeChartController::class)
            ->parameters(['size-charts' => 'size_chart'])
            ->except(['show']);
        Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])
            ->name('reviews.approve');
        Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])
            ->name('reviews.reject');
        Route::resource('reviews', ReviewController::class)
            ->except(['show']);
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
