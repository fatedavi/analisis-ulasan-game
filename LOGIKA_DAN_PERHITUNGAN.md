# Dokumentasi Proyek: Analisis Sentimen Ulasan Arknights: Endfield

> File ini menjelaskan **logika**, **perhitungan**, **sumber data**, dan **file** yang terlibat dalam proyek ini.
> Dibaca dari atas ke bawah, hanya perlu memahami bagian 1–3 untuk ikut serta.

---

## 1. Gambaran Besar

Proyek ini adalah **aplikasi web Django (Laravel)** yang menampilkan hasil **analisis sentimen** ulasan game _Arknights: Endfield_ dari Google Play Store.

Sistem terdiri dari **dua bagian** yang saling terhubung:

1. **Pipeline ML (offline)** — `ml-pipeline/` → mentah → bersih → label → model → artefak
2. **Aplikasi Web (read-only)** — Laravel + React/Inertia → membaca artefak → tampilkan di dashboard

Penting: **aplikasi web tidak melatih model, tidak scraping, dan tidak memproses teks untuk pertama kali.** Semua perhitungan dilakukan di fase offline (pipeline), hasilnya tersimpan sebagai berkas di `storage/app/artifacts`. Web hanya membaca berkas tersebut.

```
[Data ulasan] → [Pipeline ML offline] → [Artefak: CSV/JSON] → [Aplikasi Web (Laravel)]
                                     ↑
                              (teks, label, metrik)
```

---

## 2. Sumber Data (dari mana data berasal & file apa yang memuatnya)

### 2.1 Data utama (saat ini)

| File                              | Lokasi      | Baris                 | Kolom                            | Peran                                                |
| --------------------------------- | ----------- | --------------------- | -------------------------------- | ---------------------------------------------------- |
| `arknights_endfield_reviews2.csv` | Root proyek | 3.760 data + 1 header | `username, rating, review, date` | **Data ulasan mentah** yang diimpor aplikasi Laravel |

> Ini adalah data yang paling aktif dipakai saat ini oleh aplikasi web. Dijalankan perintah `php artisan artifacts:import` (default file-nya `arknights_endfield_reviews2.csv`).

### 2.2 Data pipeline ML (referensi)

| File                                      | Lokasi                        | Isi                                                                         |
| ----------------------------------------- | ----------------------------- | --------------------------------------------------------------------------- |
| `data/raw/reviews_raw.csv`                | `ml-pipeline/data/raw/`       | ulasan hasil _scraping_ Google Play Store (hasil crawl 22 Mei 2026)         |
| `data/manual/labels_annotated.csv`        | `ml-pipeline/data/manual/`    | Label manual 3 anotator (kolom `ann1/ann2/ann3`)                            |
| `data/processed/reviews_selected.csv`     | `ml-pipeline/data/processed/` | Hasil seleksi, baris tetap ada, nilai `included`/`exclusion_reason`         |
| `data/processed/reviews_labeled.csv`      | `ml-pipeline/data/processed/` | Label final (majority vote)                                                 |
| `data/processed/reviews_preprocessed.csv` | `ml-pipeline/data/processed/` | Setelah pra-pemrosesan teks                                                 |
| `data/processed/data_summary.json`        | `ml-pipeline/data/processed/` | Ringkasan jumlah eksklusi/class distribution                                |
| `requirements.txt`                        | `ml-pipeline/`                | Pustaka Python: pandas, scikit-learn, Sastrawi, joblib, google-play-scraper |

> Catatan: folder `data/` ini **tidak ada** di repositori ini. Hanya ada referensinya di kode (`ml-pipeline/src/*.py`). Yang ada di repositori adalah **dataset CSV di root** (`arknights_endfield_reviews2.csv`) yang diolah oleh aplikasi Laravel.

### 2.3 File yang diperlukan aplikasi web

Tiga berkas **wajib** membaca aplikasi:

1. `run_meta.json` → `run_id`, `demo`, `generated_at`
2. `results.json` → metrik + 12 kombinasi parameter
3. `oof_predictions.csv` → prediksi out-of-fold (sumber sumber angka sentimen)

Dua berkas **tambahan** (opsional, untuk fitur tertentu):

- `words.json` → word cloud
- `reviews.json` → halaman daftar ulasan

Lokasi default: `storage/app/artifacts/` (dikonfigurasi di `config/artifacts.php`; bisa diubah lewat env `ARTIFACTS_PATH`).

---

## 3. Aliran Logika (bagian krusial)

### 3.1 Di sisi Laravel: impor CSV + klasifikasi

plik `app/Support/CsvReviewImporter.php` melakukan impor:

```
1. Baca CSV baris per baris
2. Kumpulkan: username, rating, review, date
3. normalisasi rating: 1-5 (selain itu diabaikan)
4. Labeling label_true (proxy dari rating):
     rating 4-5 → positif (1)
     rating 1-2 → negatif (0)
     rating 3  → netral → diEKSKLUSI (tidak masuk model)
5. Tokenisasi teks:
     lowercase, split pada non-huruf, ambil token ≥ 3 karakter, filter stopwords
6. label_pred (prediksi):
     - gunakan LexiconClassifier (kamus kata kunci)
     - jika ditemukan kata kunci → label_pred = hasil klasifikasi, basis = "teks"
     - jika tidak ada kata kunci → label_pred = label_true (rating), proba 0.5, basis = "rating"
7. Simpan artefak ke storage/app/artifacts/
```

Tokenisasi: `preg_split('/[^a-zA-Z]+/u', mb_strtolower($text))`, lalu filter `mb_strlen($token) >= 3` dan bukan stopwords.

### 3.2 LexiconClassifier (kalsifikasi leksikon teks)

Ini adalah **satu-satunya prediksi berbasis teks** di aplikasi.

```
Untuk setiap token dalam teks:
    - jika token == negator (tidak, tak, ga, kaga, dsb) → toggle "negated"
    - jika negated:
          token positif  → count negatif (maksud sebenarnya negatif)
          token negatif  → count positif (maksud sebenarnya positif)
    - jika token positif → positiveHits++
    - jika token negatif → negativeHits++

proba_pos = (positiveHits + 0.5) / (totalHits + 1)
label     = proba_pos >= 0.5 ? 'positif' : 'negatif'
```

> Daftar POSITIVE/NEGATIVE/NEGATORS ada di `app/Support/LexiconClassifier.php`
> (posisi 16–49, 46–49).

### 3.3 Perhitungan sentimen (summary)

Dari `app/Support/ArtifactRepository.php` (method `summary()`):

```
total    = jumlah baris oof_predictions.csv
positive = jumlah label_pred == 1
negative = total - positive

sentiment_score = round(100 × positive / total)   → 0-100
```

Contoh: `positive=2600, total=3500` → `sentiment_score = round(100×2600/3500) = 74`.

Invariant: `positive + negative = total`.

### 3.4 Evaluasi model (confusion matrix & metrik)

Dari `app/Support/ArtifactRepository.php` (method `evaluation()`):

```
setiap baris oof: label_true (0/1), label_pred (0/1)

TP = true=1, pred=1
TN = true=0, pred=0
FP = true=0, pred=1
FN = true=1, pred=0

accuracy  = (TP + TN) / total
precision = TP / (TP + FP)
recall    = TP / (TP + FN)
F1        = 2·precision·recall / (precision + recall)

macro (precision/recall/F1) = rata-rata dari kelas positif & negatif
```

Baseline mayoritas: `max(positive, negative) / total` (akurasi kalau cuma tebak kelas terbanyak).

### 3.3 Kode: LexiconClassifier.php (penjelasan per blok)

File `LexiconClassifier.php` adalah **satu-satunya** komponen yang melakukan prediksi sentimen dari teks. Berikut kodenya dengan penjelasan per blok:

```php
<?php
namespace App\Support;

class LexiconClassifier
{
    // ---------- [1] DAMMET LIST/kamus ---------- //
    /** Kata-kata yang menandakan sentimen POSITIF */
    private const POSITIVE = [
        'bagus', 'mantap', 'mantab', 'keren', 'seru', 'asyik', 'asik', 'suka',
        'senang', 'menarik', 'mantul', 'kece', 'gacor', 'nyaman', 'indah',
        'lancar', 'mulus', 'terbaik', 'tergila', 'rekomendasi', 'recommend',
        'recommended', 'recomended', 'top', 'best', 'great', 'good', 'nice',
        'wow', 'luar', 'ok', 'oke', 'sip', 'optimal', 'kualitas', 'wajib',
        'candu', 'bikin', 'ketagihan', 'sepuas', 'memuaskan', 'puas', 'bangga',
        'favorit', 'juara', 'lengkap', 'presisi', 'halus', 'yes', 'mantep',
        'kerenn', 'kerennn', 'cakep', 'gokil', 'nagih', 'smooth', 'epic',
        'perfect', 'amazing', 'sempurna', 'memukau', 'menyenangkan', 'seneng',
        'bersyukur', 'memanjakan', 'baguss', 'bgus', 'bagusss', 'anjay',
        'sukses', 'masterpiece', 'enak',
    ];

    /** Kata-kata yang menandakan sentimen NEGATIF */
    private const NEGATIVE = [
        'buruk', 'jelek', 'parah', 'payah', 'sampah', 'busuk', 'benci', 'bodoh',
        'tolol', 'dongo', 'kesal', 'jengkel', 'kecewa', 'mengecewakan', 'muak',
        'lag', 'lagging', 'error', 'crash', 'bug', 'force', 'close', 'stuck',
        'banned', 'hang', 'ribet', 'susah', 'sulit', 'bosan', 'membosankan',
        'menyesal', 'frustasi', 'bangkrut', 'greedy', 'mahal', 'nguras', 'boros',
        'kuota', 'buang', 'pay', 'worst', 'bad', 'terrible', 'trash', 'sucks',
        'jeblok', 'lemah', 'lambat', 'kun', 'scam', 'ngulang', 'ngulangin',
        'keulang', 'keulangin', 'ulang', 'diulang', 'mengulang', 'reset',
        'kereset', 'malah', 'sia', 'habis', 'muak', 'males', 'malas', 'capek',
        'cape', 'kesel', 'ngeselin', 'burik', 'pelit', 'gagal', 'rusak',
        'melelahkan', 'bermasalah', 'nyesel',
    ];

    /** Kata negasi yang "membalik" makna kata sesudahnya */
    private const NEGATORS = [
        'tidak', 'tak', 'gak', 'ga', 'nggak', 'enggak', 'bukan', 'belum',
        'jangan', 'tanpa', 'kurang', 'kagak', 'ogah',
    ];

    // ---------- [2] FUNGSI UTAMA: classify() ---------- //
    /**
     * Klasifikasi satu teks ulasan.
     * Mengembalikan array: label, proba_pos, detected.
     * null bila teks tidak punya token valid.
     */
    public function classify(string $text): ?array
    {
        // [3] Case folding: ubah jadi huruf kecil
        $tokens = preg_split('/[^a-zA-Z]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));

        // [4] Jika hasil tokenisasi kosong → tidak bisa klasifikasi
        if ($tokens === []) {
            return null;
        }

        $positiveHits = 0;   // jumlah kata kunci positif yang "nyata" (bukan dibalik)
        $negativeHits = 0;   // jumlah kata kunci negatif yang "nyata"
        $negated = false;    // flag: kata berikutnya dibalik maknanya?

        // ---------- [5] Looping per token ---------- //
        foreach ($tokens as $token) {
            // [6] Jika ketemu negator, "toggle" flag untuk token sesudahnya
            if (in_array($token, self::NEGATORS, true)) {
                $negated = true;
                continue;
            }

            // [7] Jika flag "negated" aktif, kasih label terbalik
            if ($negated) {
                if (in_array($token, self::POSITIVE, true)) {
                    $negativeHits++;   // "bagus" yang dibalik → makna negatif
                } elseif (in_array($token, self::NEGATIVE, true)) {
                    $positiveHits++;   // "buruk" yang dibalik → makna positif
                }
                $negated = false;      // reset flag
                continue;
            }

            // [8] Normal: kasih label sesuai daftar
            if (in_array($token, self::POSITIVE, true)) {
                $positiveHits++;
            } elseif (in_array($token, self::NEGATIVE, true)) {
                $negativeHits++;
            }
        }

        // ---------- [9] Hitung probabilitas ---------- //
        $totalHits = $positiveHits + $negativeHits;
        $detected  = $totalHits > 0;
        // +0.5 dan +1 sebagai "smoothing" kecil biar never 0/1
        $probaPos  = $totalHits > 0 ? ($positiveHits + 0.5) / ($totalHits + 1) : 0.5;

        // ---------- [10] Output ---------- //
        return [
            'label'     => $probaPos >= 0.5 ? 'positif' : 'negatif',
            'proba_pos' => round($probaPos, 4),
            'detected'  => $detected,
        ];
    }
}
```

**Penjelasan alur per blok:**

| Blok    | Berisi                                                | Inti                                   |
| ------- | ----------------------------------------------------- | -------------------------------------- |
| [1]     | 3 konstanta array: `POSITIVE`, `NEGATIVE`, `NEGATORS` | Kamus kata kunci + kata negasi         |
| [2]     | method `classify(string $text)`                       | Titik masuk untuk klasifikasi          |
| [3]     | `preg_split('/[^a-zA-Z]+/u', mb_strtolower($text))`   | Hapus non-huruf + lowercase            |
| [4]     | Cek token kosong                                      | Kalau hanya emoji/simbol → `null`      |
| [5]–[8] | Looping token + flag `$negated`                       | Kata negasi "membalik" kata sesudahnya |
| [9]     | `proba_pos = (positiveHits + 0.5) / (totalHits + 1)`  | Smoothing biar nggak 0 atau 1          |
| [10]    | `label = proba_pos >= 0.5 ? 'positif' : 'negatif'`    | Ambang batas 0.5                       |

**Rumus inti** (biasanya disebut "score"):

```
proba_pos = (jumlah kata positif yang "nyata" + 0.5) / (total kata + 1)
label     = proba_pos ≥ 0.5 ? "positif" : "negatif"
```

> Contoh: `"banyak bug, gak bagus"`
>
> - `bug` → negatif (+1 negatif), `gak` → negator, `bagus` → diputar → jadi 1 positif
> - `positiveHits=0, negativeHits=1`
> - `proba_pos = (0 + 0.5) / (1 + 1) = 0.25` → label **negatif**

Ada **12 kombinasi** yang diuji:

```
k     = 5 atau 10
alpha = 0.01, 0.1, 0.5, 1.0, 1.5, 2.0
→ 2 × 6 = 12
```

Pembagian fold memakai stratifikasi label (seed 42):

- **K=5**: 5 fold; tiap iterasi 4 fold latih + 1 fold uji (5 iterasi).
- **K=10**: 10 fold; fold dipasangkan `(1,2), (3,4), …` menjadi 5 iterasi,
  masing-masing 8 fold latih + 2 fold uji. Metrik dilaporkan per fold (10 titik),
  tetapi setiap sampel tetap diuji tepat sekali sehingga OOF valid.

TF-IDF di-fit ulang hanya dari dokumen latih pada setiap fold (mencegah
kebocoran data uji), lalu Multinomial NB dilatih untuk setiap `alpha`.

Pilih yang terbaik berdasarkan urutan (BL-49):

1. `metrics.macro.f1.mean` tertinggi
2. rical `metrics.accuracy.mean` lebih tinggi
3. `metrics.macro.f1.std` lebih kecil
4. `alpha` lebih kecil
5. `k` lebih besar

Hasil konfigurasi terbaik di artefak demo: `alpha=1.0, k=10`.

### 3.6 Halaman `/visualisasi` (gambar & tabel)

- **Confusion Matrix**: matriks akumulasi All fold (baris=aktual, kolom=prediksi)
- **Grid Eksperimen**: 12 baris (k, alpha, macro_f1, accuracy), baris terbaik diberi `BEST`
- **Metrik rata-rata K-Fold**: accuracy, macro F1, F1 positif, F1 negatif
- **Validasi K-Fold**: tombol K=5/K=10, diagram pembagian fold + iterasi,
  tabel metrik per fold (akurasi, macro F1, F1 per kelas), dan bar chart per fold

### 3.7 Halaman `/ulasan-pengguna`

Dari `reviews.json`, filter (label/pred_label, rating, pencarian teks), lalu paginasi (12 per halaman). Tampilkan:

- rating (bintang), username, tanggal, teks ulasan
- label prediksi + keyakinan (`proba_pos`)
- `review_id` + fold

---

## 4. Tabel Perhitungan (rumus yang dipakai)

| Metrik                       | Rumus                                            |
| ---------------------------- | ------------------------------------------------ |
| `sentiment_score`            | `round(100 × positive / total)`                  |
| `baseline_majority_accuracy` | `max(positive, negative) / total`                |
| `accuracy`                   | `(TP + TN) / (TP + TN + FP + FN)`                |
| `precision` (kelas c)        | `TP_c / (TP_c + FP_c)`                           |
| `recall` (kelas c)           | `TP_c / (TP_c + FN_c)`                           |
| `F1` (kelas c)               | `2·P·R / (P + R)`                                |
| `macro`                      | rata-rata sederhana dari kelas positif & negatif |
| `share` (word cloud)         | `count / total_tokens`                           |
| fold index (di Laravel)      | `((id_ulasan - 1) % 10) + 1`                     |

---

## 5. Peta File: Siapa yang Membuat & Membaca

### 5.1 Filename → Tugas → Algoritma

| File                                          | Buat/Mengubah?     | Tugas                                          | Algoritma/Perhitungan                                 |
| --------------------------------------------- | ------------------ | ---------------------------------------------- | ----------------------------------------------------- |
| `arknights_endfield_reviews2.csv`             | Input data         | 3.760 baris ulasan                             | —                                                     |
| `app/Support/CsvReviewImporter.php`           | Tulis artefak      | Impor CSV, label_true, tokenize, tulis artefak | Proxy rating, stopword, tokenisasi                    |
| `app/Support/LexiconClassifier.php`           | Membaca (instansi) | Klasifikasi teks leksikon                      | hitungan kata kunci + negasi                          |
| `app/Support/ArtifactRepository.php`          | Baca artefak       | Ringkasan, evaluasi, review, kata              | rumus metrik OOF                                      |
| `app/Support/BestConfigSelector.php`          | Baca artefak       | Pilih kombinasi terbaik (BL-49)                | urutan kriteria                                       |
| `ml-pipeline/src/scrape.py`                   | (referensi)        | Scrape Google Play                             | google-play-scraper                                   |
| `ml-pipeline/src/select_data.py`              | (referensi)        | Seleksi, soft-delete                           | duplicate/empty/unclear/out_of_range                  |
| `ml-pipeline/src/labeling.py`                 | (referensi)        | Majority vote 3 anotator + Fleiss' kappa       | persentase kesepakatan                                |
| `ml-pipeline/src/preprocess.py`               | (referensi)        | Pra-pemrosesan teks                            | case fold, cleaning, token, stopword, stemming        |
| `ml-pipeline/src/train_eval.py`               | (referensi)        | Training & evaluasi                            | TfidfVectorizer + MultinomialNB + K-Fold 12 kombinasi |
| `resources/stopwords-id.txt`                  | Input              | Daftar stopwords Indonesia                     | 369 kata                                              |
| `ml-pipeline/README.md`                       | Dokumentasi        | Cara menjalankan pipeline                      | —                                                     |
| `ml-pipeline/ARTIFACT_CONTRACT.md`            | Kontrak            | Format artefak aplikasi                        | —                                                     |
| `PRD_Analisis_Sentimen_Arknights_Endfield.md` | Dokumentasi        | PRD lengkap (business logic)                   | —                                                     |

### 5.2 Alur penggunaan

```
1. data: arknights_endfield_reviews2.csv ada di root
2. jalankan: php artisan artifacts:import
   → tulis artefak ke storage/app/artifacts/
3. buka: /
   → /api/summary, /api/evaluation, /healthz (API read-only)
```

---

## 6. Skema Database (Laravel)

Database hanya untuk **autentikasi & sesi** (auth/Fortify), bukan untuk data ulasan:

- `users` — nama, email, password, 2FA
- `sessions` — sesi Laravel
- `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `passkeys`, `password_reset_tokens`

> Data ulasan **tidak disimpan di database**. Semua artefak disimpan di `storage/app/artifacts/`.

---

## 7. Cara Kerja Kode (singkat per file utama)

### 7.1 `app/Support/CsvReviewImporter.php`

- `import()` → baca CSV, proses, tulis artefak ke `config('artifacts.path')`
- `tokenize()` → lowercase, pisah non-huruf, ≥3 char, filter stopwords
- `process()` → aturan label_true:
    - rating 3 → `unclear` (eksklusi)
    - rating 4-5 → `positif`, 1-2 → `negatif`
- `KFoldEvaluator` → 12 config (K × alpha), metrik per fold + agregat, OOF config terbaik
- `MultinomialNaiveBayes` → TF-IDF fit per fold lalu Multinomial NB
- `wordFrequencies()` → untuk word cloud
- `results()` → grid, `fold_plans`, metrik per fold, dan `best`

### 7.2 `app/Support/LexiconClassifier.php`

- `classify($text)` → hitung `positiveHits`, `negativeHits` (dengan negator).
- `proba_pos = (positiveHits + 0.5) / (totalHits + 1)`
- label = `positif` jika `proba_pos >= 0.5`, selain itu `negatif`

### 7.3 `app/Support/BestConfigSelector.php`

- Urutkan 12 kombinasi sesuai kriteria BL-49

### 7.4 `ml-pipeline/src/train_eval.py`

- Grid: `k ∈ {5,10}` × `alpha ∈ {0.01..2.0}` = **12**
- `StratifiedKFold(n_splits=k, shuffle=True, random_state=42)`
- `TfidfVectorizer()` (default) → `MultinomialNB(alpha)`
- Evaluasi fold: accuracy, macro F1, per kelas
- `best_of()`: macro-F1 tertinggi (tie-break sesuai BL-49)
- `oof_predictions()`: cross_val_predict → `oof_predictions.csv`

### 7.5 `ml-pipeline/src/labeling.py`

- `majority_vote()`: 3 anotator → label_final (1 jika ≥2 "positif")
- `fleiss_kappa()`: Cohen's kappa antar pasangan anotator, rata-rata

### 7.6 `ml-pipeline/src/preprocess.py`

- Urutan: case folding → cleaning → tokenizing → stopword filter → stemming
- `preprocess()` dipakai training dan inference (mencegah train/serve skew)

---

## 8. Struktur Data dalam Artefak

### 8.1 `oof_predictions.csv` (sumber semua angka di web)

```csv
review_id,label_true,label_pred,proba_pos,fold
r000001,1,1,0.87,1
r000002,0,0,0.21,1
```

- `label_true`: 1=positif, 0=negatif (dari rating)
- `label_pred`: 1=positif, 0=negatif (prediksi OOF Multinomial NB config terbaik)
- `proba_pos`: keyakinan 0-1
- `fold`: 1-10 (fold tempat sampel menjadi data uji)

### 8.2 `data_summary.json`

```json
{
    "raw": 3760,
    "excluded": {
        "duplicate": 0,
        "empty": 0,
        "unclear": 0,
        "out_of_range": 0,
        "empty_after_preprocessing": 0
    },
    "included": 3500,
    "class_distribution": { "positif": 2600, "negatif": 900 },
    "label_agreement": { "percentage": 94.6, "fleiss_kappa": 0.907 }
}
```

Invariant: `raw = Σ excluded + included`; `included = Σ class_distribution`.

### 8.3 `results.json`

```json
{
  "run_id": "abc123",
  "best": { "alpha": 1.0, "k": 10 },
  "baseline_majority_accuracy": 0.74,
  "fold_plans": {
    "5": { "k": 5, "fold_count": 5, "test_folds_per_iteration": 1, "iterations": [...] },
    "10": { "k": 10, "fold_count": 10, "test_folds_per_iteration": 2, "iterations": [...] }
  },
  "configs": [
    {
      "k": 10, "alpha": 1.0, "folds": 10,
      "effective_folds": 10, "test_folds_per_iteration": 2, "iterations": 5,
      "metrics": { "accuracy": {"mean": 0.9, "std": 0.01}, "macro": {...}, "...": "..." },
      "per_fold": [ { "fold": 1, "iteration": 1, "accuracy": 0.91, "macro": {...} }, "... 10 total" ]
    },
    "... 12 total"
  ]
}
```

### 8.4 `reviews.json`

```json
{
    "run_id": "abc123",
    "total_docs": 3500,
    "reviews": [
        {
            "review_id": "r000001",
            "username": "...",
            "rating": 5,
            "review": "...",
            "date": "...",
            "true_label": "positif",
            "pred_label": "positif",
            "proba_pos": 0.87,
            "fold": 1,
            "basis": "teks"
        }
    ]
}
```

### 8.5 `words.json`

```json
{
    "run_id": "abc123",
    "total_docs": 3500,
    "total_tokens": 48211,
    "words": [
        { "token": "game", "count": 1204, "label": "positif", "share": 0.025 }
    ]
}
```

---

## 9. Contoh Perhitungan (kasus konkret)

### Kasus 1: Label dari rating

Ulasan: `rating=5, review="Game bagus dan seru"`

- `label_true` = 1 (positif) karena rating 4-5
- Tokenisasi: `['game', 'bagus', 'seru']` (filters stopwords `dan`)
- Lexicon: `bagus` (positif), `seru` (positif) → `positiveHits=2, negativeHits=0`
- `proba_pos = (2 + 0.5) / (2 + 1) = 0.8333`
- `label_pred = positif`, basis = `teks`

### Kasus 2: Negasi

Ulasan: `rating=1, review="Banyak bug, gak bagus"`

- Token: `['banyak', 'bug', 'gak', 'bagus']`
- `gak` = negator → toggle
- `bagus` (positif) di-negasi → `negativeHits++`
- `positiveHits=0, negativeHits=1`
- `proba_pos = (0 + 0.5) / (1 + 1) = 0.25`
- `label_pred = negatif`, basis = `teks`

### Kasus 3: Tanpa kata kunci

Ulasan: `rating=4, review="OKay"` → setelah tokenisasi hanya `['okay']`

- `okay` ada di POSITIVE list → `positiveHits=1` (dari daftar)
- Maka `proba_pos` tetap ada

> Catatan: kalau teks **tidak punya token valid** (hanya emoji/huruf non-ASCII), `classify()` mengembalikan `null`, lalu `textPrediction()` fallback ke `label_true`, `proba_pos=0.5`, `basis='rating'`.

---

## 10. Preview UI → Endpoint → File (peta)

| Bagian UI                          | Controller                    | API  | File yang membaca                     |
| ---------------------------------- | ----------------------------- | ---- | ------------------------------------- |
| `/` Halaman Analisis Sentimen      | `SentimentAnalysisController` | —    | `run_meta.json`, `words.json`         |
| `/ulasan-pengguna` Daftar ulasan   | `SentimentReviewController`   | —    | `reviews.json`                        |
| `/visualisasi` Evaluasi            | `VisualizationController`     | —    | `results.json`, `oof_predictions.csv` |
| `/api/summary` Ringkasan dashboard | `Api/SummaryController`       | GET  | OOF dari `oof_predictions.csv`        |
| `/api/evaluation` Evaluasi         | `Api/EvaluationController`    | GET  | `results.json`, `oof_predictions.csv` |
| `/api/healthz` Status              | `Api/HealthController`        | GET  | semua artefak                         |
| `/api/predict` Prediksi ulasan     | `Api/PredictController`       | POST | `LexiconClassifier` (demo)            |

---

## 11. Kata Kunci Penting (istilah)

| Istilah             | Arti                                                                    |
| ------------------- | ----------------------------------------------------------------------- |
| `label_true`        | Label dari rating (proxy): 1=positif, 0=negatif, 3=netral (dikeluarkan) |
| `label_pred`        | Label prediksi: 1=positif, 0=negatif                                    |
| `proba_pos`         | Keyakinan bahwa ulasan positif (0-1)                                    |
| `oof`               | Out-Of-Fold: prediksi dari cross-validation (bukan training data)       |
| `baseline majority` | Akurasi kalau selalu tebak kelas terbanyak                              |
| `K-Fold`            | Validasi silang: data dibagi K bagian, masing-masing jadi uji sekali    |
| `alpha`             | Smooth parameter MultinomialNB                                          |
| `macro`             | Rata-rata sederhana dua kelas                                           |
| `stopwords`         | Kata fungsi yang diabaikan (tidak, dari, adalah, dsb)                   |
| `negator`           | Kata yang membalik makna (tidak, gak, belum, dsb)                       |
| `artifact`          | Berkas keluaran pipeline (CSV/JSON di `storage/app/artifacts`)          |

---

## 12. Flow Kerja (gambar alir sederhana)

```
[Arkhnights ulasan CSV]
        ↓
[Artifacts:Import (CsvReviewImporter)]
   - normalisasi rating
   - label_true (4-5→1, 1-2→0, 3→eksklusi)
   - tokenize (stopword, ≥3 char)
   - LexiconClassifier → label_pred, proba_pos
   - fold (1-10)
        ↓
[Artefak → storage/app/artifacts/]
   run_meta.json, data_summary.json, results.json,
   oof_predictions.csv, words.json, reviews.json
        ↓
[Web (read-only)]
   /api/summary, /api/evaluation, /healthz, /api/predict,
   / (analisis), /ulasan-pengguna, /visualisasi
```

---

## 13. Referensi

- `ml-pipeline/README.md` — cara menjalankan pipeline ML
- `ml-pipeline/ARTIFACT_CONTRACT.md` — format artefak
- `PRD_Analisis_Sentimen_Arknights_Endfield.md` — PRD lengkap (business logic, aturan BL/FR/NFR)
- `config/artifacts.php` — lokasi artefak
- `routes/web.php`, `routes/api.php` — route aplikasi
- `tests/` — test fitur & unit (pastikan argumen tetap berlaku)
