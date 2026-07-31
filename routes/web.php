<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\SpecialtyController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\VersionFileController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('project.access')->group(function () {
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('project.show');

        Route::get('/projects/{project}/subcategories/{subcategory}', [SubcategoryController::class, 'show'])
            ->name('subcategory.show');

        Route::middleware('role:designer,admin,super_admin')->group(function () {
            Route::post('/projects/{project}/specialties/{specialty}/subcategories', [SubcategoryController::class, 'store'])
                ->name('subcategory.store');
            Route::patch('/subcategories/{subcategory}', [SubcategoryController::class, 'update'])
                ->name('subcategory.update');
            Route::delete('/subcategories/{subcategory}', [SubcategoryController::class, 'destroy'])
                ->name('subcategory.destroy');

            Route::post('/subcategories/{subcategory}/versions', [VersionController::class, 'store'])
                ->name('version.store');
            Route::delete('/versions/{version}', [VersionController::class, 'destroy'])
                ->name('version.destroy');

            Route::post('/versions/{version}/files/{language}', [VersionFileController::class, 'store'])
                ->name('version-file.store');
            Route::delete('/versions/{version}/files/{language}', [VersionFileController::class, 'destroy'])
                ->name('version-file.destroy');
        });

        Route::get('/versions/{version}/files/{language}/{kind}/download', [VersionFileController::class, 'download'])
            ->whereIn('kind', ['dwg', 'doc'])
            ->name('version-file.download');

        Route::get('/versions/{version}/files/{language}/dxf', [VersionFileController::class, 'dxf'])
            ->name('version-file.dxf');
    });

    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

        Route::post('/teams/{team}/specialties', [SpecialtyController::class, 'store'])->name('specialties.store');
        Route::patch('/specialties/{specialty}', [SpecialtyController::class, 'update'])->name('specialties.update');
        Route::delete('/specialties/{specialty}', [SpecialtyController::class, 'destroy'])->name('specialties.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
