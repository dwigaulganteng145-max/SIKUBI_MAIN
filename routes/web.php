<?php

use App\Http\Controllers\AnomalyController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect('/dashboard'));

require __DIR__ . '/auth.php';

// ── All authenticated users ──
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');

    // Profile — everyone can edit their own
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Settings — read-only for Direktur, CRUD for Admin (enforced in controller)
    Route::get('/settings/categories', [SettingsController::class, 'categories'])->name('settings.categories');

    // Reports & Exports
    Route::get('/reports/recap', [ReportController::class, 'recapCsv'])->name('reports.recap');
    Route::get('/reports/recap/excel', [ReportController::class, 'recapExcel'])->name('reports.excel');
    Route::get('/reports/print', [ReportController::class, 'printRecap'])->name('reports.print');
});

// ── Admin Keuangan only ──
Route::middleware(['auth', 'verified', 'role:ADMIN_KEUANGAN'])->group(function () {
    // Transactions (edit)
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');

    // CSV Import
    Route::get('/import', [CsvImportController::class, 'index'])->name('import.index');
    Route::post('/import', [CsvImportController::class, 'store'])->name('import.store');
    Route::delete('/import/{batch}', [CsvImportController::class, 'destroy'])->name('import.destroy');
    Route::post('/import/{id}/restore', [CsvImportController::class, 'restore'])->name('import.restore');
    Route::delete('/import/{id}/force', [CsvImportController::class, 'forceDestroy'])->name('import.forceDestroy');
    Route::post('/import/force-batch', [CsvImportController::class, 'forceDestroyBatch'])->name('import.forceDestroyBatch');
    Route::post('/import/duplicates/resolve-batch', [CsvImportController::class, 'resolveBatch'])->name('import.resolveBatch');

    // Bank Accounts
    Route::get('/accounts', [BankAccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [BankAccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [BankAccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [BankAccountController::class, 'destroy'])->name('accounts.destroy');

    // Anomalies
    Route::get('/anomalies', [AnomalyController::class, 'index'])->name('anomalies.index');
    Route::post('/anomalies/detect', [AnomalyController::class, 'detect'])->name('anomalies.detect');
    Route::patch('/anomalies/{id}', [AnomalyController::class, 'review'])->name('anomalies.review');

    // Settings (CRUD)
    Route::post('/settings/categories', [SettingsController::class, 'storeCategory'])->name('settings.categories.store');
    Route::delete('/settings/categories/{category}', [SettingsController::class, 'destroyCategory'])->name('settings.categories.destroy');
    Route::patch('/settings/categories/{category}/approve', [SettingsController::class, 'approveCategory'])->name('settings.categories.approve');
});

// ── Direktur only ──
Route::middleware(['auth', 'verified', 'role:DIREKTUR'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
