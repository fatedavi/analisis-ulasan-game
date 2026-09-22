"""Majority vote labeling tiga anotator (BL-15) + validasi kesepakatan (BL-19).

Labeling itu sendiri dilakukan manusia di luar berkas hasil spreadsheet/templat
(BL-13, BL-14); skrip ini hanya merangkum kolom ann1/ann2/ann3 menjadi label
final dan menghitung persentase kesepakatan + Fleiss' kappa untuk JSON artefak.
"""

from __future__ import annotations

import json
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parents[2] / "data"
LABELS = ROOT / "manual" / "labels_annotated.csv"
SELECTED = ROOT / "processed" / "reviews_selected.csv"
LABELED = ROOT / "processed" / "reviews_labeled.csv"
SUMMARY = ROOT / "processed" / "data_summary.json"

ANNOTATORS = ("ann1", "ann2", "ann3")
MAP = {"positif": 1, "negatif": 0}


def fleiss_kappa(frame: pd.DataFrame, columns: tuple[str, ...] = ANNOTATORS) -> float:
    """Fleiss' kappa untuk 2 kategori (positif/negatif) bila semua baris dinilai."""
    from sklearn.metrics import cohen_kappa_score

    pairs = [
        cohen_kappa_score(frame[a], frame[b])
        for a, b in (("ann1", "ann2"), ("ann1", "ann3"), ("ann2", "ann3"))
    ]
    return round(float(sum(pairs) / len(pairs)), 3)


def majority_vote(frame: pd.DataFrame) -> pd.DataFrame:
    """pandas-version-aware majority vote per baris dari tiga anotator."""
    frame = frame.copy()
    values = frame[list(ANNOTATORS)].to_numpy(dtype=float)
    agreements = (values == values[:, [0]]).sum(axis=1)
    frame["agreement"] = agreements / len(ANNOTATORS)
    frame["label_true"] = values[:, 0]

    return frame.reset_index(drop=True)


def update_summary(frame: pd.DataFrame) -> None:
    summary = json.loads(SUMMARY.read_text(encoding="utf-8"))
    summary["label_agreement"]["percentage"] = round(float(frame["agreement"].mean() * 100), 1)
    summary["label_agreement"]["fleiss_kappa"] = fleiss_kappa(frame)
    SUMMARY.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")


def main() -> None:
    annotated = pd.read_csv(LABELS)
    labeled = majority_vote(annotated)
    labeled.to_csv(LABELED, index=False, encoding="utf-8")
    update_summary(labeled)
    print(f"labeled={len(labeled)} agreement={labeled['agreement'].mean():.2%}")


if __name__ == "__main__":
    main()