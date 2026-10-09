<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/budgets', [DashboardController::class, 'storeBudget'])->name('budgets.store');
    Route::post('/expenses', [DashboardController::class, 'storeExpense'])->name('expenses.store');
    Route::patch('/expenses/{expense}', [DashboardController::class, 'updateExpense'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [DashboardController::class, 'destroyExpense'])->name('expenses.destroy');
    Route::post('/invoices', [DashboardController::class, 'storeInvoice'])->name('invoices.store');
    Route::patch('/invoices/{invoice}/mark-paid', [DashboardController::class, 'markInvoicePaid'])->name('invoices.mark-paid');
    Route::patch('/invoices/{invoice}', [DashboardController::class, 'updateInvoice'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [DashboardController::class, 'destroyInvoice'])->name('invoices.destroy');
    Route::get('/dashboard/export-csv/{type}', [DashboardController::class, 'exportCsv'])->name('csv.export');
    Route::post('/dashboard/import-csv', [DashboardController::class, 'importCsv'])->name('csv.import');
    Route::get('/invoices/{invoice}/pdf', [DashboardController::class, 'invoicePdf'])->name('invoices.pdf');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
