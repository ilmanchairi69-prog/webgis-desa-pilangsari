<?php

use App\Http\Controllers\Admin\AdminMapController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HouseController;
use App\Http\Controllers\Admin\MapFeatureController;
use App\Http\Controllers\Admin\MapLayerController;
use App\Http\Controllers\Admin\PublicationCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicComplaintController;
use App\Http\Controllers\PublicMapController;
use App\Http\Controllers\WebsiteVisitStatisticsController;
use App\Http\Middleware\RecordWebsiteVisit;
use Illuminate\Support\Facades\Route;

Route::middleware(RecordWebsiteVisit::class)->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home')->block();
    Route::view('/profil', 'public.profil')->name('profil')->block();
    Route::view('/pemerintahan', 'public.pemerintahan')->name('pemerintahan')->block();
    Route::view('/informasi', 'public.agenda')->name('informasi')->block();
    Route::view('/agenda', 'public.agenda')->name('agenda')->block();
    Route::get('/peta', [PublicMapController::class, 'index'])->name('map')->block();
});
Route::post('/pengaduan', [PublicComplaintController::class, 'store'])->middleware('throttle:5,10')->name('complaints.store');
Route::get('/statistik-kunjungan', WebsiteVisitStatisticsController::class)->name('visits.statistics');
Route::get('/api/map/public', [PublicMapController::class, 'data'])->name('api.map.public');

Route::get('/Admin/login', [AuthenticationController::class, 'create'])->name('login.public-alias');
Route::get('/admin/login', [AuthenticationController::class, 'create'])->name('login');
Route::post('/admin/login', [AuthenticationController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('login.store');
Route::post('/admin/logout', [AuthenticationController::class, 'destroy'])->middleware(['auth', 'active'])->name('logout');

// Dashboard dan pengelolaan rumah dapat digunakan Super Admin maupun Admin RT aktif.
// Verifikasi, publikasi, fasilitas, pengguna, dan audit tetap khusus Super Admin.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/map', [AdminMapController::class, 'index'])->name('map');

    Route::get('/houses', [HouseController::class, 'index'])->name('houses.index');
    Route::get('/houses/create', [HouseController::class, 'create'])->name('houses.create');
    Route::post('/houses', [HouseController::class, 'store'])->name('houses.store');
    Route::get('/houses/{house}', [HouseController::class, 'show'])->name('houses.show');
    Route::get('/houses/{house}/edit', [HouseController::class, 'edit'])->name('houses.edit');
    Route::get('/houses/{house}/archive', [HouseController::class, 'archiveConfirmation'])->name('houses.archive.confirm');
    Route::put('/houses/{house}', [HouseController::class, 'update'])->name('houses.update');
    Route::post('/houses/{house}/archive', [HouseController::class, 'archive'])->name('houses.archive');

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::get('/complaints/{complaint}/attachment', [ComplaintController::class, 'download'])->name('complaints.download');
        Route::patch('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
        Route::get('/publication', [PublicationCategoryController::class, 'index'])->name('publication.index');
        Route::patch('/publication/{systemKey}', [PublicationCategoryController::class, 'update'])->name('publication.update');
        Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
        Route::post('/houses/{house}/verify', [VerificationController::class, 'verify'])->name('houses.verify');
        Route::post('/houses/{house}/reject', [VerificationController::class, 'reject'])->name('houses.reject');
        Route::post('/houses/{house}/publish', [VerificationController::class, 'publish'])->name('houses.publish');
        Route::post('/houses/{house}/unpublish', [VerificationController::class, 'unpublish'])->name('houses.unpublish');

        // CRUD objek peta hanya untuk Super Admin. Admin RT mengelola titik rumah
        // melalui alur HouseController dan tidak boleh membuat objek peta bebas.
        // Pengaturan layer hanya untuk Super Admin. Layer baru tetap draft/private;
        // Super Admin harus mengaktifkan publikasi secara eksplisit.
        Route::get('/layers', [MapLayerController::class, 'index'])->name('layers.index');
        Route::get('/layers/create', [MapLayerController::class, 'create'])->name('layers.create');
        Route::post('/layers', [MapLayerController::class, 'store'])->name('layers.store');
        Route::get('/layers/{layer}/edit', [MapLayerController::class, 'edit'])->name('layers.edit');
        Route::put('/layers/{layer}', [MapLayerController::class, 'update'])->name('layers.update');
        Route::delete('/layers/{layer}', [MapLayerController::class, 'destroy'])->name('layers.destroy');

        Route::get('/features', [MapFeatureController::class, 'index'])->name('features.index');
        Route::get('/features/create', [MapFeatureController::class, 'create'])->name('features.create');
        Route::post('/features', [MapFeatureController::class, 'store'])->name('features.store');
        Route::get('/features/{feature}', [MapFeatureController::class, 'show'])->name('features.show');
        Route::get('/features/{feature}/edit', [MapFeatureController::class, 'edit'])->name('features.edit');
        Route::put('/features/{feature}', [MapFeatureController::class, 'update'])->name('features.update');
        Route::delete('/features/{feature}', [MapFeatureController::class, 'destroy'])->name('features.destroy');

        Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
        Route::get('/facilities/create', [FacilityController::class, 'create'])->name('facilities.create');
        Route::post('/facilities', [FacilityController::class, 'store'])->name('facilities.store');
        Route::get('/facilities/{facility}/edit', [FacilityController::class, 'edit'])->name('facilities.edit');
        Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('facilities.update');
        Route::post('/facilities/{facility}/publish', [FacilityController::class, 'publish'])->name('facilities.publish');
        Route::post('/facilities/{facility}/unpublish', [FacilityController::class, 'unpublish'])->name('facilities.unpublish');
        Route::post('/facilities/{facility}/archive', [FacilityController::class, 'archive'])->name('facilities.archive');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

// Alias login agar tombol publik dapat menggunakan /login.
Route::redirect('/login', '/admin/login', 302)->name('login.shortcut');
