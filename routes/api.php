<?php

use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PredictController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

/**
 * API hanya membaca artefak (BL-53); tidak ada training/scraping saat request.
 * Artefak hilang/rusak → HTTP 503, kecuali /api/predict yang memakai leksikon
 * demo dan mengembalikan 422 untuk input di luar batas (BL-61/62).
 */
/**
 * API hanya membaca artefak (BL-53); tidak ada training/scraping saat request.
 * Artefak hilang/rusak → HTTP 503, kecuali /api/predict yang memakai leksikon
 * demo dan mengembalikan 422 untuk input di luar batas (BL-61/62).
 *
 * Catatan: `withRouting(api:)` di bootstrap/app.php sudah menambahkan prefix
 * `/api` untuk berkas ini — jangan membungkusnya dengan Route::prefix('api')
 * lagi agar URL tidak menjadi /api/api/*.
 */
Route::get('/summary', SummaryController::class)->name('api.summary');
Route::get('/evaluation', EvaluationController::class)->name('api.evaluation');
Route::get('/healthz', HealthController::class)->name('api.health');
Route::post('/predict', PredictController::class)->name('api.predict');
