"""Pra-pemrosesan teks (BL-21 s.d. BL-30).

Satu fungsi `preprocess` dipakai identik oleh training dan inference
(BL-28) untuk mencegah train/serve skew. Baris yang menghasilkan 0 token
ditandai `empty_after_preprocessing` dan dikeluarkan. Versi stopword dan
Sastrawi dicatat di run_meta.json.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parents[2] / "data"
LABELED = ROOT / "processed" / "reviews_labeled.csv"
PREPROCESSED = ROOT / "processed" / "reviews_preprocessed.csv"
SUMMARY = ROOT / "processed" / "data_summary.json"

URL_RE = re.compile(r"http\S+|www\.\S+")
HANDLE_RE = re.compile(r"@\w+")
HASHTAG_RE = re.compile(r"#\w+")
MULTISPACE_RE = re.compile(r"\s+")

STOPWORDS_PATH = Path(__file__).resolve().parents[2] / "resources" / "stopwords-id.txt"


def load_stopwords(path: Path = STOPWORDS_PATH) -> set[str]:
    if path.is_file():
        return {line.strip() for line in path.read_text(encoding="utf-8").splitlines() if line.strip()}
    try:
        from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

        return set(StopWordRemoverFactory().get_stop_words())
    except ImportError:
        return set()


def preprocess(text: str, stopwords: set[str]) -> list[str]:
    """Ubah ulasan mentah menjadi daftar token ter-stem (identik train/inference)."""
    lowered = str(text).lower()
    cleaned = MULTISPACE_RE.sub(" ", URL_RE.sub(" ", HANDLE_RE.sub(" ", HASHTAG_RE.sub(" ", lowered))))
    tokens_nostop = [token for token in cleaned.split() if token not in stopwords]

    stemmed = stem(tokens_nostop)
    tokens = sorted(set(stemmed))

    return tokens


def stem(tokens: list[str]) -> list[str]:
    try:
        from Sastrawi.Stemmer.StemmerFactory import StemmerFactory

        return [StemmerFactory().create_stemmer().stem(token) for token in tokens]
    except ImportError:
        return tokens


def build_frame(labels: pd.DataFrame, stopwords: set[str]) -> pd.DataFrame:
    """Terapkan preprocess + case folding (text_lower) + kolom antara BL-29."""
    rows = [
        {
            "review_id": row.reviewId,
            "label_true": row.label_true,
            "text_lower": row.content.lower(),
            "text_clean": MULTISPACE_RE.sub(" ", URL_RE.sub(" ", HANDLE_RE.sub(" ", HASHTAG_RE.sub(" ", row.content.lower())))).strip(),
            "tokens_filtered": " ".join(t for t in row.content.lower().split() if t not in stopwords),
            "tokens_stemmed": " ".join(stem([t for t in row.content.lower().split() if t not in stopwords])),
            "text_final": " ".join(preprocess(row.content, stopwords)),
            "n_tokens": len(preprocess(row.content, stopwords)),
        }
        for row in labels.itertuples(index=False)
    ]
    return pd.DataFrame(rows)


def mark_empty(frame: pd.DataFrame) -> pd.DataFrame:
    """Label 'empty_after_preprocessing' untuk 0 token (BL-27)."""
    empty = frame["n_tokens"].eq(0)
    frame.loc[empty, "label_true"] = pd.NA
    return frame


def persist(processed: pd.DataFrame, stopwords_version: str, sastrawi_ok: bool) -> None:
    summary = json.loads(SUMMARY.read_text(encoding="utf-8"))
    summary["excluded"]["empty_after_preprocessing"] = int(processed["n_tokens"].eq(0).sum())
    summary["included"] = int(processed["n_tokens"].gt(0).sum())
    SUMMARY.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")
    meta = {
        "stopwords_source": str(STOPWORDS_PATH if STOPWORDS_PATH.is_file() else "Sastrawi"),
        "sastrawi_available": sastrawi_ok,
        "stopwords_version": stopwords_version,
    }
    (ROOT / "processed" / "preprocess_meta.json").write_text(
        json.dumps(meta, indent=2, ensure_ascii=False), encoding="utf-8"
    )


def main() -> None:
    labels = pd.read_csv(LABELED)
    stopwords = load_stopwords()
    processed = build_frame(labels, stopwords)
    processed = mark_empty(processed)
    processed[processed["label_true"].notna()].to_csv(PREPROCESSED, index=False, encoding="utf-8")
    persist(processed, "cache-token-unik", stopwords_detect_sastrawi())


def stopwords_detect_sastrawi() -> bool:
    try:
        from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

        return bool(StopWordRemoverFactory())
    except ImportError:
        return False


if __name__ == "__main__":
    main()