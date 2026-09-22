# ml-pipeline

Komponen A (offline) — referensi pipeline ML untuk Analisis Sentimen Ulasan
Arknights: Endfield. Struktur mengikuti proposal (PRD §3.1) dan keluaran
mengikuti `ARTIFACT_CONTRACT.md` agar bisa dibaca aplikasi web tanpa perubahan kode.

## Tahapan

```
data/raw/reviews_raw.csv        -- scrape.py          (BL-01, jalankan sekali)
data/manual/labels_annotated.csv-- labeling manual      (BL-13 s.d. BL-19)
data/processed/*                -- select_data.py + labeling.py + preprocess.py
artifacts/*                     -- python -m src.train_eval (FR-P06, 12 kombinasi)
```

## Perintah

```bash
pip install google-play-scraper pandas scikit-learn Sastrawi joblib
python -m src.scrape              # (opsional) ambil ulasan -> data/raw/reviews_raw.csv
python -m src.select_data         # soft-delete + data_summary.json
python -m src.labeling            # majority vote 3 anotator + Fleiss' kappa
python -m src.preprocess          # token/stemming konsisten (BL-28)
python -m src.train_eval          # grid 12 -> results/oof/run_meta (FR-P05, AC-06)
```

Berhenti di sini bila tidak ingin menjalankan pipeline: aplikasi web sudah bisa
berjalan dengan **artefak demo** (`php artisan artifacts:demo`). Hasil run nyata
cukup menimpa berkas di `storage/app/artifacts`.

## Catatan

- `preprocess()` dipakai identik oleh training dan inference (BL-28). Sastrawi
  hanya stub opsional di referensi ini.
- Keempat berkas wajib (`run_meta.json`, `results.json`, `oof_predictions.csv`,
  `data_summary.json`) harus ada; bila hilang aplikasi menampilkan empty state.