"""Scraping ulasan Google Play Store untuk Arknights: Endfield (BL-01).

Dijalankan sekali; hasil dibekukan sebagai berkas CSV mentah dan bukan bagian
dari perintah atomik pipeline (`python -m src.train_eval`). Pengguna harus sudah
mendapat persetujuan *reviewer agreement* pada Play Console sebelum menjalankan.
"""

from __future__ import annotations

import argparse
from pathlib import Path

APP_ID = "com.Hypergryph.ArknightsEndfield"
REQUIRED_COLUMNS = [
    "reviewId",
    "content",
    "score",
    "at",
    "thumbsUpCount",
    "appVersion",
    "scraped_at",
]


def scrape(app_id: str, count: int, out: Path) -> None:
    """Ambil `count` ulasan via google-play-scraper dan tulis CSV mentah.

    Kolom privasi (`userName`, `userImage`) langsung dibuang pada tahap ini
    sesuai asumsi kesepakatan review (BL-01). Google Play membatasi pagination,
    jadi `count` praktis dibatasi ±1000 ulasan per pendekatan berurutan.
    """
    from google_play_scraper import reviews, Sort

    result, _token = reviews(
        app_id,
        lang="id",
        country="id",
        sort=Sort.NEWEST,
        count=count,
    )

    rows = [
        {col: review.get(col) for col in REQUIRED_COLUMNS}
        for review in result
    ]
    out.parent.mkdir(parents=True, exist_ok=True)
    write_csv(out, rows)


def write_csv(path: Path, rows: list[dict]) -> None:
    import csv

    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=REQUIRED_COLUMNS)
        writer.writeheader()
        writer.writerows(rows)


def main() -> None:
    parser = argparse.ArgumentParser(description="Scraping ulasan Play Store")
    parser.add_argument("--app", default=APP_ID)
    parser.add_argument("--count", type=int, default=1200)
    parser.add_argument(
        "--out",
        type=Path,
        default=Path(__file__).resolve().parents[2] / "data" / "raw" / "reviews_raw.csv",
    )
    args = parser.parse_args()
    scrape(args.app, args.count, args.out)
    print(f"Ditulis: {args.out}")


if __name__ == "__main__":
    main()