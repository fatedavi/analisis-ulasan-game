"""Training & evaluasi Multinomial Naive Bayes - grid 12 kombinasi.

Perintah atomik: `python -m src.train_eval` menjalankan K-Fold untuk 12
konfigurasi, memilih yang terbaik (BL-49), dan menulis artefak. Deterministik
karena menjalankan seed tetap (BL-53, AC-06). Struktur JSON mengikuti
ARTIFACT_CONTRACT.md sehingga dapat menimpa artefak demo tanpa perubahan kode web.
"""

from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score, f1_score, precision_recall_fscore_support
from sklearn.model_selection import StratifiedKFold, cross_val_predict
from sklearn.naive_bayes import MultinomialNB

ROOT = Path(__file__).resolve().parents[2]
DATA = ROOT / "data" / "processed"
ARTIFACTS = ROOT / "artifacts"

ALPHAS = [0.01, 0.1, 0.5, 1.0, 1.5, 2.0]
FOLDS = [5, 10]
SEED = 42


def load_frame() -> pd.DataFrame:
    return pd.read_csv(DATA / "reviews_preprocessed.csv", dtype={"review_id": str})


def build_matrix(frame: pd.DataFrame, vectorizer: TfidfVectorizer) -> np.ndarray:
    return vectorizer.fit_transform([str(x) for x in frame["text_final"].tolist()])


def splitter(k: int) -> StratifiedKFold:
    return StratifiedKFold(n_splits=k, shuffle=True, random_state=SEED)


def per_fold_scores(matrix, labels, config: dict) -> tuple[list[float], list[float], list[np.ndarray]]:
    """Accuracy, macro-F1, dan [P,R,F1] per fold untuk satu konfigurasi."""
    accs, macro_f1s, class_rows = [], [], []
    for train_idx, test_idx in splitter(config["k"]).split(matrix, labels):
        model = MultinomialNB(alpha=config["alpha"]).fit(matrix[train_idx], labels[train_idx])
        y_pred = model.predict(matrix[test_idx])
        accs.append(accuracy_score(labels[test_idx], y_pred))
        macro_f1s.append(f1_score(labels[test_idx], y_pred, labels=[1, 0], average="macro"))
        class_rows.append(precision_recall_fscore_support(labels[test_idx], y_pred, labels=[1, 0]))
    return accs, macro_f1s, class_rows


def stat(per_fold: np.ndarray) -> dict:
    return {"mean": round(float(per_fold.mean()), 4), "std": round(float(per_fold.std()), 4)}


def evaluate(config: dict, matrix, labels: np.ndarray) -> dict:
    """Hasilkan blok metrics.konfigurasi sesuai kontrak (positif=1, negatif=0)."""
    accs, macro_f1s, class_rows = per_fold_scores(matrix, labels, config)
    per_fold_matrix = np.stack([row_arr for row_arr in class_rows])  # folds x (3 metrics x 2 classes)

    positive = per_fold_matrix[:, :, 0]
    negative = per_fold_matrix[:, :, 1]

    config["metrics"] = {
        "accuracy": stat(np.array(accs)),
        "positive": {"precision": stat(positive[:, 0]), "recall": stat(positive[:, 1]), "f1": stat(positive[:, 2])},
        "negative": {"precision": stat(negative[:, 0]), "recall": stat(negative[:, 1]), "f1": stat(negative[:, 2])},
        "macro": {
            "precision": stat(np.stack([positive[:, 0], negative[:, 0]]).mean(axis=0)),
            "recall": stat(np.stack([positive[:, 1], negative[:, 1]]).mean(axis=0)),
            "f1": stat(np.array(macro_f1s)),
        },
    }
    return config


def best_of(results: list[dict]) -> dict:
    """BL-49: macro-F1 mean terbesar; tie-break accuracy, std-F1, alpha, k."""
    ranked = sorted(
        results,
        key=lambda r: (
            -r["metrics"]["macro"]["f1"]["mean"],
            -r["metrics"]["accuracy"]["mean"],
            r["metrics"]["macro"]["f1"]["std"],
            r["alpha"],
            -r["k"],
        ),
    )
    return {"alpha": ranked[0]["alpha"], "k": ranked[0]["k"]}


def oof_predictions(frame: pd.DataFrame, best: dict) -> pd.DataFrame:
    """Prediksi out-of-fold konfigurasi terbaik -> oof_predictions.csv (kontrak)."""
    matrix = build_matrix(frame, TfidfVectorizer())
    labels = frame["label_true"].to_numpy()
    fold = splitter(best["k"])

    model = MultinomialNB(alpha=best["alpha"])
    y_pred = cross_val_predict(model, matrix, labels, cv=fold)
    y_proba_pos = cross_val_predict(model, matrix, labels, cv=fold, method="predict_proba")[:, 1]

    assignments = np.full(len(frame), 0, dtype=int)
    for i, (train_idx, test_idx) in enumerate(splitter(best["k"]).split(matrix, labels), start=1):
        assignments[test_idx] = i

    return pd.DataFrame(
        {
            "review_id": frame["review_id"],
            "label_true": labels,
            "label_pred": y_pred,
            "proba_pos": np.round(y_proba_pos, 2),
            "fold": assignments,
        }
    )


def content_sha(frames: pd.DataFrame) -> str:
    return hashlib.sha256("|".join(frames["text_final"].tolist()).encode("utf-8")).hexdigest()[:6]


def write_json(filename: str, payload: dict) -> None:
    (ARTIFACTS / filename).write_text(json.dumps(payload, indent=2), encoding="utf-8")


def main() -> None:
    frame = load_frame()
    labels = frame["label_true"].to_numpy()
    matrix = build_matrix(frame, TfidfVectorizer())

    evaluated = [evaluate({"k": k, "alpha": alpha, "folds": k, "metrics": None}, matrix, labels) for k in FOLDS for alpha in ALPHAS]
    best = best_of(evaluated)
    dataset_sha = content_sha(frame)

    ARTIFACTS.mkdir(parents=True, exist_ok=True)
    write_json(
        "results.json",
        {
            "run_id": dataset_sha,
            "best": best,
            "baseline_majority_accuracy": round(float(max(labels.mean(), 1 - labels.mean())), 6),
            "configs": evaluated,
        },
    )
    oof_predictions(frame, best).to_csv(ARTIFACTS / "oof_predictions.csv", index=False)

    if (DATA / "data_summary.json").is_file():
        (ARTIFACTS / "data_summary.json").write_text((DATA / "data_summary.json").read_text(encoding="utf-8"), encoding="utf-8")

    write_json(
        "run_meta.json",
        {
            "run_id": dataset_sha,
            "seed": SEED,
            "best_alpha": best["alpha"],
            "best_k": best["k"],
            "dataset_sha256": dataset_sha,
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "demo": False,
            "source": "pipeline",
        },
    )

    import joblib

    joblib.dump(build_matrix(frame, TfidfVectorizer()), ARTIFACTS / "model.joblib")
    print(f"best={best} -> {ARTIFACTS}; hasil demo dapat ditimpa tanpa kode menangani perubahan")


if __name__ == "__main__":
    main()