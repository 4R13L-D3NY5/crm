<?php

use App\Modules\Social\Http\Controllers\InstagramController;
use App\Modules\Social\Http\Controllers\SocialCommentController;
use Illuminate\Support\Facades\Route;

Route::get('social/comments', [SocialCommentController::class, 'index'])->name('social.comments.index');
Route::post('social/facebook/sync', [SocialCommentController::class, 'syncFacebook'])->name('social.facebook.sync');
Route::post('social/instagram/sync', [InstagramController::class, 'sync'])->name('social.instagram.sync');
Route::post('social/instagram/conversations/{conversation}/messages', [InstagramController::class, 'sendMessage'])->name('social.instagram.messages.send');
Route::post('social/facebook/conversations/{conversation}/messages', [SocialCommentController::class, 'sendMessage'])->name('social.facebook.messages.send');
