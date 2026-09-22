<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Artifact Location
    |--------------------------------------------------------------------------
    |
    | Direktori tempat artefak hasil pipeline ML disimpan. Dashboard hanya
    | membaca berkas di sini (BL-53). Pipeline Python memproduksi berkas dengan
    | nama persis yang dikonfigurasi di bawah (lihat ml-pipeline/src/train_eval.py).
    |
    */

    'path' => env('ARTIFACTS_PATH') ?: storage_path('app/artifacts'),

    /*
    |--------------------------------------------------------------------------
    | Artifact Files
    |--------------------------------------------------------------------------
    */

    'run_meta' => 'run_meta.json',
    'results' => 'results.json',
    'oof_predictions' => 'oof_predictions.csv',
    'data_summary' => 'data_summary.json',

    /*
    |--------------------------------------------------------------------------
    | Word Cloud
    |--------------------------------------------------------------------------
    |
    | words.json diproduksi oleh `artifacts:import` (dan `artifacts:demo`) untuk
    | mendukung panel Cloud Kata. Bukan bagian dari pipeline ML Python.
    |
    */

    'words' => 'words.json',

    /*
    |--------------------------------------------------------------------------
    | Review Listing
    |--------------------------------------------------------------------------
    |
    | reviews.json diproduksi oleh `artifacts:import` (dan `artifacts:demo`)
    | untuk halaman Ulasan Pengguna: komentar, rating, dan hasil klasifikasi
    | per ulasan. Bukan bagian dari pipeline ML Python.
    |
    */

    'reviews' => 'reviews.json',

];
