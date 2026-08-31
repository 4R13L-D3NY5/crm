<?php

use App\Modules\Social\Http\Controllers\SocialCommentController;
use Illuminate\Support\Facades\Route;

Route::get('social/comments', [SocialCommentController::class, 'index'])->name('social.comments.index');
