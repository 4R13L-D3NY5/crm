<?php

use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Http\Controllers\ImportContactsController;
use Illuminate\Support\Facades\Route;

Route::post('contacts/import', ImportContactsController::class)->name('contacts.import');
Route::post('contacts/bulk-message', [ContactController::class, 'bulkMessage'])->name('contacts.bulk-message');
Route::apiResource('contacts', ContactController::class);
