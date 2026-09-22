# Kontrak Artefak — Pipeline ML ↔ Aplikasi Web

Dokumen ini mengikat output pipeline Python (`ml-pipeline/`) dengan pembaca artefak
di aplikasi Laravel (`app/Support/ArtifactRepository.php`). Aplikasi membaca berkas
dari direktori **`storage/app/artifacts`** (diatur via `config/artifacts.path`).

Pipeline menulis artefak ke direktori yang sama; berkas dari run nyata cukup
menimpa berkas demo yang ada tanpa perubahan kode aplikasi.

## Berkas

| Berkas                | Wajib | Dipakai aplikasi          | Ditulis pada tahap                |
| --------------------- | ----- | ------------------------- | --------------------------------- |
| `run_meta.json`       | ✅    | `run_id`, `demo`, waktu   | `train_eval.py`                   |
| `results.json`        | ✅    | metrik + konfigurasi grid | `train_eval.py`                   |
| `oof_predictions.csv` | ✅    | ringkasan sentimen        | `train_eval.py`                   |
| `data_summary.json`   | ✅    | klasifikasi ± pendukung   | `select_data.py` (disalin ke sini)|
| `model.joblib`        | —     | tidak dibaca (referensi)  | `train_eval.py`                   |
| `words.json`          | ⭕    | word cloud (aplikasi)     | `artifacts:import` / `artifacts:demo` |
| `reviews.json`        | ⭕    | halaman Ulasan Pengguna   | `artifacts:import` / `artifacts:demo` |

Jika salah satu berkas wajib hilang atau rusak (JSON tidak valid), aplikasi
mengembalikan null → endpoint 503 dan UI menampilkan pesan empty state
("Hasil analisis belum tersedia…"). `words.json` dan `reviews.json` bersifat
opsional: bila tidak ada, panel/pagera terkait hanya menampilkan petunjuk.

### `words.json`

Diproduksi **aplikasi Laravel** (`php artisan artifacts:import`, dan versi sintetis
untuk demo), bukan pipeline Python.

```jsonc
{
  "run_id": "a1b2c3",
  "total_docs": 3527,
  "total_tokens": 48211,
  "words": [
    { "token": "game", "count": 1204, "label": "positif", "share": 0.025 }
  ]
}
```

- `words` diurutkan menurun berdasarkan `count`, dibatasi N (default 200).
- `label` = kelas dominan pemilik token (`positif`/`negatif`); warna kata mengikutinya.
- `share = count / total_tokens`.

### `reviews.json`

Diproduksi **aplikasi Laravel** (`php artisan artifacts:import`, dan versi sintetis
untuk demo), bukan pipeline Python. Berisi satu baris per ulasan **included**
(label nyata + prediksi + komentar) untuk halaman Ulasan Pengguna.

```jsonc
{
  "run_id": "a1b2c3",
  "total_docs": 3478,
  "reviews": [
    {
      "review_id": "r000001",
      "username": "Pengguna Google",
      "rating": 5,
      "review": "Game bagus dan seru",
      "date": "2026-05-21 14:55:44",
      "true_label": "positif",
      "pred_label": "positif",
      "proba_pos": 0.87,
      "fold": 1
    }
  ]
}
```

- `review_id` sama dengan kunci `oof_predictions.csv`; `rating` 1-5 (bintang ulasan).
- `true_label` = label proxy rating; `pred_label` = hasil klasifikasi yang
  ditampilkan sebagai "hasil analisis" (lengkap dengan `proba_pos`).
- Invariant: `total_docs = count(reviews)`; jumlah `pred_label` per kelas ==
  `positive`/`negative` di ringkasan aplikasi.

## Impor data ulasan via aplikasi (`artifacts:import`)

`php artisan artifacts:import --file=arknights_endfield_reviews2.csv` membaca kolom
`username,rating,review,date` dan menulis seluruh artefak (incl. `words.json`) ke
`storage/app/artifacts`, menimpanya (mirip peran `train_eval.py` tetapi di sisi PHP):

- Tokenisasi berbagi konvensi `PredictService` + stopword `resources/stopwords-id.txt`.
- **Aturan label proxy rating** (keputusan pemilik proyek, penyimpangan dari BL-14
  yang memang mewajibkan label manual): rating 4-5 → positif (1); 1-2 → negatif (0);
  3 (netral) → eksklusi `unclear`. Penyimpangan dicatat di `run_meta.note`.
- `label_true` = proxy; `label_pred` = proxy + noise deterministik (~7%) agar
  confusion matrix/metrik realistis. `results.json` di-anchor ke confusion nyata itu;
  `best` tetap α=1.0, K=10.

## Bentuk JSON per berkas

### `run_meta.json`

```jsonc
{
  "run_id": "a1b2c3",
  "seed": 42,
  "best_alpha": 1.0,
  "best_k": 10,
  "dataset_sha256": "<sha256 dari reviews_labeled.csv>",
  "generated_at": "2026-09-21T10:00:00+07:00",
  "demo": false,            // wajib false/absent pada run nyata
  "source": "pipeline"
}
```

Kontrak: `source` = `"pipeline"` untuk run nyata, `"demo"` untuk artefak demo;
`demo` hanya true pada artefak demo deterministik.

### `data_summary.json`

```jsonc
{
  "raw": 3761,
  "excluded": {
    "duplicate": 41,
    "empty": 12,
    "unclear": 153,
    "out_of_range": 25,
    "empty_after_preprocessing": 30
  },
  "included": 3500,
  "class_distribution": { "negatif": 900, "positif": 2600 },
  "label_agreement": { "percentage": 94.6, "fleiss_kappa": 0.907 }
}
```

Invariant: `raw = Σ excluded + included`; `included = Σ class_distribution`.

### `results.json`

```jsonc
{
  "run_id": "a1b2c3",
  "best": { "alpha": 1.0, "k": 10 },
  "baseline_majority_accuracy": 0.72,
  "configs": [
    {
      "k": 5,
      "alpha": 1.0,
      "folds": 5,
      "metrics": {
        "accuracy":   { "mean": 0.88, "std": 0.006 },
        "positive":   { "precision": { "mean": 0.0, "std": 0.0 }, "recall": { "mean": 0.0, "std": 0.0 }, "f1": { "mean": 0.0, "std": 0.0 } },
        "negative":   { "precision": { "mean": 0.0, "std": 0.0 }, "recall": { "mean": 0.0, "std": 0.0 }, "f1": { "mean": 0.0, "std": 0.0 } },
        "macro":      { "precision": { "mean": 0.0, "std": 0.0 }, "recall": { "mean": 0.0, "std": 0.0 }, "f1": { "mean": 0.0, "std": 0.0 } }
      }
    }
  ]
}
```

- Setiap `mean`/`std` berupa **angka** (bukan string). Wajib memuat
  `metrics.macro.f1.mean` dan `.std` — dipakai seleksi terbaik & tampilan.
- `baseline_majority_accuracy = max(positif, negatif) / included`.

### `oof_predictions.csv`

```
review_id,label_true,label_pred,proba_pos,fold
r000001,1,1,0.87,1
r000002,0,0,0.21,1
```

- Kolom persis: `review_id,label_true,label_pred,proba_pos,fold`.
- `label_true/label_pred`: 1 = positif, 0 = negatif. `proba_pos`: float.
- Invariant: `tp + fp + fn + tn = included`, dan
  `tp + fn = class_distribution.positif`, `tn + fp = class_distribution.negatif`.

## Seleksi konfigurasi terbaik (BL-49)

Kriteria berurutan, identik di `ml-pipeline/src/train_eval.py` dan
`app/Support/BestConfigSelector.php`:

1. `metrics.macro.f1.mean` tertinggi
2. `metrics.accuracy.mean` lebih tinggi
3. `metrics.macro.f1.std` lebih kecil
4. `alpha` lebih kecil
5. `k` lebih besar

## Grid yang diuji (BL-39)

`ALPHAS = [0.01, 0.1, 0.5, 1.0, 1.5, 2.0]` × `FOLDS = [5, 10]` → **12 konfigurasi**.

## Ringkasan sentimen (di luar pipeline)

Aplikasi menghitung `total`, `positive`, `negative`, `sentiment_score`
dari `oof_predictions.csv` pada konfigurasi terbaik (kolom `label_pred`).
`source: "oof_predictions"` ditetapkan aplikasi, bukan pipeline.

## Produksi artefak demo

`php artisan artifacts:demo` menulis artefak demo **deterministik** (seed tetap,
semua angka dibangkitkan, bukan hardcode UI) ke direktori yang sama. Skrip referensi
di `ml-pipeline/src/` harus menghasilkan struktur di atas agar bisa menimpa tanpa
perubahan kode sisi web.