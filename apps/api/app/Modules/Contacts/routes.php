<?php

use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Http\Controllers\ContactReconciliationController;
use App\Modules\Contacts\Http\Controllers\ImportContactsController;
use Illuminate\Support\Facades\Route;

Route::post('contacts/reconcile/preview', [ContactReconciliationController::class, 'preview'])->name('contacts.reconcile.preview');
Route::post('contacts/reconcile/execute', [ContactReconciliationController::class, 'execute'])->name('contacts.reconcile.execute');
Route::get('contacts/reconcile/rules', [ContactReconciliationController::class, 'getRules'])->name('contacts.reconcile.rules');
Route::post('contacts/reconcile/rules', [ContactReconciliationController::class, 'saveRule'])->name('contacts.reconcile.rules.save');
Route::delete('contacts/reconcile/rules/{id}', [ContactReconciliationController::class, 'deleteRule'])->name('contacts.reconcile.rules.delete');

Route::post('contacts/import', ImportContactsController::class)->name('contacts.import');
Route::post('contacts/bulk-message', [ContactController::class, 'bulkMessage'])->name('contacts.bulk-message');
Route::apiResource('contacts', ContactController::class);
