<?php

use App\Http\Controllers\User\AbsensiController;
use Illuminate\Support\Facades\Route; 
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\UserAuthController; 
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AbsensiController as AdminAbsensiController;
use App\Http\Controllers\Admin\IzinController as AdminIzinController; 
use App\Http\Controllers\Admin\AkunController as AdminAkunController;
use App\Http\Controllers\User\AbsensiController as UserAbsensiController;
use App\Http\Controllers\User\EnrollmentController;
use App\Http\Controllers\User\IzinController as UserIzinController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\RiwayatController;
use App\Http\Controllers\Internal\InternalEmbeddingController;
use App\Http\Controllers\Admin\EnrollmentController as  AdminEnrollmentController;

Route::post('/login/admin', [AdminAuthController::class, 'login']);
Route::post('/login/user',  [UserAuthController::class, 'login']);

  
 Route::middleware(['auth:sanctum', 'ability:role:admin'])->prefix('admin')->group(function () {
//  Route::middleware([])->prefix('admin')->group(function () {
    Route::post('/logout',                      [AdminAuthController::class, 'logout']);
    Route::get('/me',                           [AdminAuthController::class, 'me']);
    Route::get('/users',                        [UserController::class, 'index']);
    Route::get('/users/{id}',                   [UserController::class, 'show']);
    Route::post('/users',                       [UserController::class, 'store']);
    Route::put('/users/{id}',                   [UserController::class, 'update']);
    Route::delete('/users/{id}',                [UserController::class, 'destroy']);
    Route::patch('/users/{id}/status',          [UserController::class, 'toggleStatus']);
    Route::get('/users/{id}/riwayat',           [UserController::class, 'riwayat']);
    Route::get('/enrollments',                        [AdminEnrollmentController::class, 'index']);
    Route::post('/enrollments/{enrollment}/approve',  [AdminEnrollmentController::class, 'approve']);
    Route::post('/enrollments/{enrollment}/reject',   [AdminEnrollmentController::class, 'reject']);
    Route::post('/users/{user}/toggle-enrollment',    [AdminEnrollmentController::class, 'toggleEnrollment']);
    Route::get('/enrollments/{enrollment}/stream',   [AdminEnrollmentController::class, 'streamVideo']);
    Route::get('/enrollments/{enrollment}/download', [AdminEnrollmentController::class, 'downloadVideo']);
    Route::get('/absensi',                      [AdminAbsensiController::class, 'index']);
    Route::get('/absensi/rekap',                [AdminAbsensiController::class, 'rekap']);
    Route::get('/absensi/export',               [AdminAbsensiController::class, 'export']);
    Route::post('/absensi/scan',                [UserAbsensiController::class, 'scan']);
    Route::delete('/absensi/{id}',              [AdminAbsensiController::class, 'destroy']);
    Route::get('/izin',                         [AdminIzinController::class, 'index']); 
    Route::get('/akun',                         [AdminAkunController::class, 'index']);
    Route::patch('/akun/{id}/toggle',           [AdminAkunController::class, 'toggle']);
    Route::post('/akun/{id}/reset-password',    [AdminAkunController::class, 'resetPassword']);
    Route::delete('/akun/{id}',                 [AdminAkunController::class, 'destroy']);
    Route::post('/users/{id}/foto-profile',     [UserController::class, 'uploadFoto']);
    Route::delete('/users/{id}/foto-profile',   [UserController::class, 'deleteFoto']);
    Route::get('/izin/{id}',                    [AdminIzinController::class, 'show']);
    Route::patch('/izin/{id}/validasi',         [AdminIzinController::class, 'validasi']);
    Route::patch('/izin/{id}/perpanjang',       [AdminIzinController::class, 'perpanjang']);  
    Route::delete('/izin/{id}',                 [AdminIzinController::class, 'destroy']);
    Route::get('/izin/{id}/notif',              [AdminIzinController::class, 'riwayatNotif']);
    Route::get('/notif',                        [AdminIzinController::class, 'semuaNotif']);
});
  
Route::middleware(['auth:sanctum', 'ability:role:user'])->prefix('user')->group(function () {
 
    Route::post('/logout',              [UserAuthController::class, 'logout']);
    Route::get('/me',                   [UserAuthController::class, 'me']);
    Route::post('/absensi/scan',        [UserAbsensiController::class, 'scan']);
    Route::get('/absensi/cek',          [UserAbsensiController::class, 'cekStatus']);
    Route::get('/absensi/status',       [UserAbsensiController::class, 'cekStatus']);
    Route::get('/absensi/riwayat',      [UserAbsensiController::class, 'riwayat']); 
    Route::post('/absensi/validasiLokasi', [UserAbsensiController::class, 'validasiLokasi']);
    Route::get('/enrollment/status',    [EnrollmentController::class, 'checkStatus']);
    Route::post('/enrolment',           [EnrollmentController::class, 'store']);
    Route::post('/izin',                [UserIzinController::class, 'ajukan']);
    Route::get('/izin/riwayat',         [UserIzinController::class, 'riwayat']);
    Route::get('/profile',              [ProfileController::class, 'show']);
    Route::post('/profile/foto',        [ProfileController::class, 'uploadFoto']);
    Route::delete('/profile/foto',      [ProfileController::class, 'deleteFoto']);
    Route::post('/izin',                [UserIzinController::class, 'ajukan']);
    Route::get('/izin/riwayat',         [UserIzinController::class, 'riwayat']);
    Route::get('/izin/sisa-slot',       [UserIzinController::class, 'sisaSlot']);     
    Route::get('/izin/aktif',           [UserIzinController::class, 'izinAktif']);   
    Route::get('/riwayat',              [RiwayatController::class, 'index']);
    Route::get('/riwayat/ringkasan',    [RiwayatController::class, 'ringkasan']);
    Route::delete('/riwayat',           [RiwayatController::class, 'hapusSemua']);
    Route::delete('/riwayat/{id}',      [RiwayatController::class, 'hapusSatu']);
});

Route::post('/internal/embedding/store', [InternalEmbeddingController::class, 'store']);
    Route::get('/enrollments/{enrollment}/video', [AdminEnrollmentController::class, 'streamVideo']);