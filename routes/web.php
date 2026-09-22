<?php

use App\Http\Controllers\SentimentAnalysisController;
use App\Http\Controllers\SentimentReviewController;
use App\Http\Controllers\VisualizationController;
use Illuminate\Support\Facades\Route;

Route::get('/', SentimentAnalysisController::class)->name('home');

Route::get('/ulasan-pengguna', SentimentReviewController::class)->name('sentiment.reviews');

Route::get('/visualisasi', VisualizationController::class)->name('sentiment.visualization');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
