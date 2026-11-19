<?php

use App\Http\Controllers\BorrowLogReportController;
use App\Http\Controllers\EquipmentReportController;
use App\Models\ActivityLog;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (config('nativephp-internal.running') && User::query()->doesntExist()) {
        return redirect()->route('setup');
    }

    return redirect()->route($request->user() ? 'dashboard' : 'login');
})->name('home');

Route::livewire('setup', 'pages::setup')->name('setup');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->can('access-application')->name('dashboard');
    Route::livewire('equipment', 'pages::equipment.index')->can('viewAny', Equipment::class)->name('equipment.index');
    Route::livewire('equipment/categories', 'pages::equipment.categories')->can('viewAny', EquipmentCategory::class)->name('equipment.categories');
    Route::livewire('equipment/create', 'pages::equipment.form')->can('create', Equipment::class)->name('equipment.create');
    Route::livewire('equipment/{equipment}/edit', 'pages::equipment.form')->can('update', 'equipment')->name('equipment.edit');
    Route::livewire('equipment/{equipment}', 'pages::equipment.show')->can('view', 'equipment')->name('equipment.show');
    Route::livewire('borrow-log', 'pages::borrowing.index')->can('viewAny', BorrowTransaction::class)->name('borrowing.index');
    Route::livewire('borrow-log/create', 'pages::borrowing.create')->can('create', BorrowTransaction::class)->name('borrowing.create');
    Route::livewire('borrow-log/{transaction}/return', 'pages::borrowing.return')->can('returnEquipment', 'transaction')->name('borrowing.return');
    Route::livewire('borrow-log/{transaction}', 'pages::borrowing.show')->can('view', 'transaction')->name('borrowing.show');
    Route::get('equipment/report/print', [EquipmentReportController::class, 'print'])->can('viewAny', Equipment::class)->name('equipment.report.print');
    Route::get('equipment/report/export', [EquipmentReportController::class, 'export'])->can('viewAny', Equipment::class)->name('equipment.report.export');
    Route::get('borrow-log/report/print', [BorrowLogReportController::class, 'print'])->can('viewAny', BorrowTransaction::class)->name('borrowing.report.print');
    Route::get('borrow-log/report/export', [BorrowLogReportController::class, 'export'])->can('viewAny', BorrowTransaction::class)->name('borrowing.report.export');
    Route::livewire('activity-logs', 'pages::activity-logs.index')->can('viewAny', ActivityLog::class)->name('activity-logs.index');
    Route::livewire('activity-logs/{log}', 'pages::activity-logs.show')->can('view', 'log')->name('activity-logs.show');
});

require __DIR__.'/settings.php';
