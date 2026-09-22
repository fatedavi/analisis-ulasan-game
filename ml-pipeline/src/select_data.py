"""Seleksi data ulasan (BL-07 s.d. BL-12) dengan penghapusan lunak.

Semua baris yang dikeluarkan ditandai `included=False` + `exclusion_reason`,
bukan dihapus fisik. Alasan: duplicate, empty, unclear, out_of_range. Alasan
`empty_after_preprocessing` ditambahkan oleh preprocess.py. Jumlah per alasan
ditulis ke `data_summary.json` (artefak), disalin pipeline ke direktori artefak.
"""

from __future__ import annotations

import json
from dataclasses import asdict, dataclass
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parents[2] / "data"
RAW = ROOT / "raw" / "reviews_raw.csv"
PROCESSED = ROOT / "processed"
SELECTED = PROCESSED / "reviews_selected.csv"
SUMMARY = PROCESSED / "data_summary.json"

SCORE_MIN = 1
SCORE_MAX = 5


@dataclass
class Summary:
    raw: int
    excluded: dict[str, int]
    included: int
    class_distribution: dict[str, int]
    label_agreement: dict[str, float]


def load_raw(path: Path = RAW) -> pd.DataFrame:
    return pd.read_csv(path, dtype={"reviewId": str})


def select(frame: pd.DataFrame) -> pd.DataFrame:
    """Terapkan soft-delete dan catat alasan eksklusi per baris."""
    frame = frame.copy()
    frame["included"] = True
    frame["exclusion_reason"] = pd.NA

    reason = pd.Series("", index=frame.index, dtype="object")

    reason[frame["content"].isna() | (frame["content"].str.strip() == "")] = "empty"
    reason[frame["reviewId"].duplicated(keep="first")] = "duplicate"
    reason[frame["score"].isna() | frame["score"].lt(SCORE_MIN) | frame["score"].gt(SCORE_MAX)] = "out_of_range"
    reason[frame["content"].str.strip().str.len().lt(8)] = "unclear"

    frame["exclusion_reason"] = reason.replace("", pd.NA)
    frame["included"] = frame["exclusion_reason"].isna()

    return frame


def write_summary(frame: pd.DataFrame, target: Path = SUMMARY) -> dict:
    excluded = {
        label: int((frame["exclusion_reason"] == label).sum())
        for label in ("duplicate", "empty", "unclear", "out_of_range", "empty_after_preprocessing")
    }
    summary = Summary(
        raw=int(frame["reviewId"].nunique()),
        excluded=excluded,
        included=int(frame["included"].sum()),
        class_distribution={},
        label_agreement={"percentage": 0.0, "fleiss_kappa": 0.0},
    )
    data = asdict(summary)
    data["class_distribution"]["positif"] = int((frame["label_true"] == 1).sum())
    data["class_distribution"]["negatif"] = int((frame["label_true"] == 0).sum())

    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")
    return data


def main() -> None:
    frame = load_raw()
    selected = select(frame)
    selected.to_csv(SELECTED, index=False, encoding="utf-8")
    write_summary(selected)
    print(f"raw={len(selected)} selected={int(selected['included'].sum())}")


if __name__ == "__main__":
    main()