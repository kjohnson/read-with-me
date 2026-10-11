<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageRecordingController;
use App\Http\Controllers\ReadingAudioController;
use App\Http\Controllers\ReadingSessionController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:6,1');

    Route::get('login', [MagicLinkController::class, 'create'])->name('login');
    Route::post('login', [MagicLinkController::class, 'store'])->middleware('throttle:5,1')->name('magic-link.store');
    Route::post('login/password', [PasswordLoginController::class, 'store'])->middleware('throttle:5,1')->name('password-login.store');
});

Route::get('login/magic/{token}', [MagicLinkController::class, 'show'])->name('magic-link.show');
Route::post('login/magic/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:10,1')->name('magic-link.consume');

Route::middleware('auth')->group(function () {
    Route::post('logout', [PasswordLoginController::class, 'destroy'])->name('logout');

    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::delete('account/password', [AccountController::class, 'destroyPassword'])->name('account.password.destroy');

    Route::get('stories', [StoryController::class, 'index'])->name('stories.index');
    Route::post('stories/{story}/readings', [ReadingSessionController::class, 'store'])->name('readings.store');

    Route::get('readings', [ReadingSessionController::class, 'index'])->name('readings.index');

    Route::middleware('can:view,reading')->group(function () {
        Route::get('readings/{reading}', [ReadingSessionController::class, 'show'])->name('readings.show');
        Route::get('readings/{reading}/audio', ReadingAudioController::class)->name('readings.audio');
    });

    Route::middleware('can:update,reading')->group(function () {
        Route::get('readings/{reading}/record', [RecordingController::class, 'show'])->name('readings.record');
        Route::post('readings/{reading}/pages/{page}', [PageRecordingController::class, 'store'])->whereNumber('page')->name('readings.pages.store');
        Route::post('readings/{reading}/finish', [RecordingController::class, 'finish'])->name('readings.finish');
        Route::post('readings/{reading}/retry', [ReadingSessionController::class, 'retry'])->name('readings.retry');
    });

    Route::delete('readings/{reading}', [ReadingSessionController::class, 'destroy'])->middleware('can:delete,reading')->name('readings.destroy');
});
