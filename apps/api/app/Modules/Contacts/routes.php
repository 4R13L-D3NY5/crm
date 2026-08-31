<?php

use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Http\Controllers\ImportContactsController;
use Illuminate\Support\Facades\Route;

Route::post('contacts/import', ImportContactsController::class)->name('contacts.import');
Route::apiResource('contacts', ContactController::class);
