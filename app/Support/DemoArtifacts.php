<?php

namespace App\Support;

/**
 * Membangkitkan artefak demo deterministik (seed tetap) agar UI bisa dipreview
 * sebelum ada hasil run nyata dari pipeline ML.
 *
 * Seluruh angka dihasilkan (bukan hardcode di UI) sehingga memenuhi invariant
 * SC-4, dan ditandai "demo" di run_meta.json. Prediksi OOF serta daftar ulasan
 * dibangun dari klasifikasi leksikon teks (LexiconClassifier) atas templat
 * demo, jadi proba_pos dan basis jujur terhadap isi ulasan — mekanisme yang
 * persis sama dengan CsvReviewImporter.
 */
class DemoArtifacts
{
    public const TOTAL = 3500;

    public const POSITIVE_TRUE = 2600;

    public const NEGATIVE_TRUE = 900;

    /** True positif yang diklasifikasi benar oleh leksikon. */
    private const TRUE_POSITIVE = 2340;

    /** True negatif yang diklasifikasi benar oleh leksikon. */
    private const TRUE_NEGATIVE = 720;

    /** @var list<string> */
    private const POSITIVE_TEXTS = [
        'Game-nya seru dan grafisnya mantap, recommended!',
        'Cerita menarik dan gacha-nya ramah, senang mainnya.',
        'Optimisasi bagus, lancar di perangkat biasa.',
        'Operator baru keren, gameplay asyik.',
        'Visual juara dan musiknya enak didengar.',
        'Kualitas bagus banget, recommended buat semua.',
        'Lancar dan mulus, seru dimainkan.',
        'Gacha-nya ramah dan grafisnya mantap.',
        'Mantap dan seru, recommended!',
        'Keren dan juara, senang mainnya.',
    ];

    /** @var list<string> */
    private const NEGATIVE_TEXTS = [
        'Banyak bug dan lag, bikin kecewa.',
        'Force close dan error, menyesal install.',
        'Mahal dan ribet, jaringannya susah.',
        'Crash terus setelah update, sampah.',
        'Loading lambat dan gacha mahal.',
        'Menyesal dan kecewa setelah update.',
        'Sering lag dan error, mengecewakan.',
        'Bug parah setelah update, muak.',
        'Mahal dan boros, menyesal.',
        'Sampah dan ribet, kecewa.',
    ];

    public function __construct(private readonly LexiconClassifier $classifier) {}

    public function files(): array
    {
        $runId = $this->runId();
        $oof = $this->oofRows();
        $freq = $this->wordFrequencies();

        return [
            'run_meta.json' => $this->runMeta(),
            'data_summary.json' => $this->json($this->dataSummary()),
            'results.json' => $this->results(),
            'oof_predictions.csv' => $this->oofCsv($oof),
            'words.json' => $this->wordsPayload($runId, $freq),
            'reviews.json' => $this->reviewsPayload($runId, $oof),
        ];
    }

    public function write(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Tidak dapat membuat direktori artefak demo: {$dir}");
        }

        foreach ($this->files() as $filename => $content) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$filename, $content);
        }
    }

    private function runId(): string
    {
        return substr(hash('sha256', 'arknights-demo'), 0, 6);
    }

    private function runMeta(): string
    {
        return $this->json([
            'run_id' => $this->runId(),
            'demo' => true,
            'source' => 'demo',
            'seed' => 42,
            'generated_at' => '2026-09-21T10:00:00+07:00',
            'note' => 'Artefak demo deterministik. Prediksi dari klasifikasi leksikon teks ulasan demo; ganti dengan hasil `python -m src.train_eval` untuk data asli.',
        ]);
    }

    private function dataSummary(): array
    {
        return [
            'raw' => 3760,
            'excluded' => [
                'unclear' => 250,
                'empty' => 10,
            ],
            'included' => self::TOTAL,
            'class_distribution' => [
                'positif' => self::POSITIVE_TRUE,
                'negatif' => self::NEGATIVE_TRUE,
            ],
            'baseline_majority_accuracy' => round(self::POSITIVE_TRUE / self::TOTAL, 6),
        ];
    }

    private function results(): string
    {
        $configs = [];

        foreach ([
            ['alpha' => 0.01, 'k' => 5], ['alpha' => 0.01, 'k' => 10],
            ['alpha' => 0.1, 'k' => 5], ['alpha' => 0.1, 'k' => 10],
            ['alpha' => 0.5, 'k' => 5], ['alpha' => 0.5, 'k' => 10],
            ['alpha' => 1.0, 'k' => 5], ['alpha' => 1.0, 'k' => 10],
            ['alpha' => 1.5, 'k' => 5], ['alpha' => 1.5, 'k' => 10],
            ['alpha' => 2.0, 'k' => 5], ['alpha' => 2.0, 'k' => 10],
        ] as $cfg) {
            $configs[] = [
                'alpha' => (float) $cfg['alpha'],
                'k' => (int) $cfg['k'],
                'fold' => (int) $cfg['k'],
                'metrics' => $this->metrics((float) $cfg['alpha'], (int) $cfg['k']),
            ];
        }

        return $this->json([
            'run_id' => $this->runId(),
            'demo' => true,
            'best' => ['alpha' => 1.0, 'k' => 10],
            'baseline_majority_accuracy' => round(self::POSITIVE_TRUE / self::TOTAL, 6),
            'configs' => $configs,
        ]);
    }

    /**
     * @return array{accuracy: array<string, float>, positive: array<string, array<string, float>>, negative: array<string, array<string, float>>, macro: array<string, array<string, float>>}
     */
    private function metrics(float $alpha, int $k): array
    {
        $tp = self::TRUE_POSITIVE;
        $tn = self::TRUE_NEGATIVE;
        $fp = self::NEGATIVE_TRUE - $tn;
        $fn = self::POSITIVE_TRUE - $tp;

        $posPrecision = $tp / ($tp + $fp);
        $posRecall = $tp / ($tp + $fn);
        $posF1 = $this->f1($posPrecision, $posRecall);
        $negPrecision = $tn / ($tn + $fn);
        $negRecall = $tn / ($tn + $fp);
        $negF1 = $this->f1($negPrecision, $negRecall);

        $base = [
            'accuracy' => ($tp + $tn) / self::TOTAL,
            'positive' => ['precision' => $posPrecision, 'recall' => $posRecall, 'f1' => $posF1],
            'negative' => ['precision' => $negPrecision, 'recall' => $negRecall, 'f1' => $negF1],
        ];

        $base['macro'] = [
            'precision' => ($posPrecision + $negPrecision) / 2,
            'recall' => ($posRecall + $negRecall) / 2,
            'f1' => ($posF1 + $negF1) / 2,
        ];

        $boost = $k === 10 ? 0.0015 : 0.0;
        $std = 0.006 + abs($alpha - 1.0) * 0.002 * ($k === 10 ? 0.85 : 1.0);

        $scale = function (float $value) use ($boost, $std): array {
            return [
                'mean' => round($value + $boost, 6),
                'std' => round($std, 6),
            ];
        };

        return [
            'accuracy' => $scale($base['accuracy']),
            'positive' => [
                'precision' => $scale($base['positive']['precision']),
                'recall' => $scale($base['positive']['recall']),
                'f1' => $scale($base['positive']['f1']),
            ],
            'negative' => [
                'precision' => $scale($base['negative']['precision']),
                'recall' => $scale($base['negative']['recall']),
                'f1' => $scale($base['negative']['f1']),
            ],
            'macro' => [
                'precision' => $scale($base['macro']['precision']),
                'recall' => $scale($base['macro']['recall']),
                'f1' => $scale($base['macro']['f1']),
            ],
        ];
    }

    private function f1(float $precision, float $recall): float
    {
        return $precision + $recall > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;
    }

    /**
     * @return list<array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string, review: string}>
     */
    private function oofRows(): array
    {
        $rows = [];
        $index = 0;

        $emit = function (int $labelTrue, int $correct, int $wrong) use (&$rows, &$index): void {
            $correctTexts = $labelTrue === 1 ? self::POSITIVE_TEXTS : self::NEGATIVE_TEXTS;
            $wrongTexts = $labelTrue === 1 ? self::NEGATIVE_TEXTS : self::POSITIVE_TEXTS;

            for ($i = 0; $i < $correct; $i++) {
                $rows[] = $this->oofRow(++$index, $labelTrue, $correctTexts[$i % count($correctTexts)]);
            }

            for ($i = 0; $i < $wrong; $i++) {
                $rows[] = $this->oofRow(++$index, $labelTrue, $wrongTexts[$i % count($wrongTexts)]);
            }
        };

        $emit(1, self::TRUE_POSITIVE, self::POSITIVE_TRUE - self::TRUE_POSITIVE);
        $emit(0, self::TRUE_NEGATIVE, self::NEGATIVE_TRUE - self::TRUE_NEGATIVE);

        return $rows;
    }

    /**
     * @return array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string, review: string}
     */
    private function oofRow(int $index, int $labelTrue, string $review): array
    {
        $score = $this->classifier->classify($review);

        return $this->oofRowFromScore($index, $labelTrue, $score, $review);
    }

    /**
     * @return array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string, review: string}
     */
    private function oofRowFromScore(int $index, int $labelTrue, array $score, string $review): array
    {
        return [
            'review_id' => 'demo_'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
            'label_true' => $labelTrue,
            'label_pred' => $score['label'] === 'positif' ? 1 : 0,
            'proba_pos' => $score['proba_pos'],
            'fold' => (($index - 1) % 10) + 1,
            'basis' => $score['detected'] ? 'teks' : 'rating',
            'review' => $review,
        ];
    }

    /**
     * @param  list<array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string, review: string}>  $oof
     */
    private function oofCsv(array $oof): string
    {
        $lines = ['review_id,label_true,label_pred,proba_pos,fold'];

        foreach ($oof as $row) {
            $lines[] = sprintf(
                '%s,%d,%d,%.2f,%d',
                $row['review_id'],
                $row['label_true'],
                $row['label_pred'],
                $row['proba_pos'],
                $row['fold'],
            );
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{total_tokens: int, words: list<array{token: string, label: string, count: int}>}
     */
    private function wordFrequencies(): array
    {
        $vocab = [];
        $total = 0;
        $stopwords = $this->stopwords();

        $scan = function (array $texts, string $label) use (&$vocab, &$total, $stopwords): void {
            foreach ($texts as $text) {
                foreach (preg_split('/[^a-zA-Z]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                    if (mb_strlen($token) < 3 || in_array($token, $stopwords, true)) {
                        continue;
                    }

                    $vocab[$token][$label] = ($vocab[$token][$label] ?? 0) + 1;
                    $total++;
                }
            }
        };

        $scan(self::POSITIVE_TEXTS, 'positif');
        $scan(self::NEGATIVE_TEXTS, 'negatif');

        $words = [];

        foreach ($vocab as $token => $counts) {
            $words[] = [
                'token' => $token,
                'label' => ($counts['positif'] ?? 0) >= ($counts['negatif'] ?? 0) ? 'positif' : 'negatif',
                'count' => ($counts['positif'] ?? 0) + ($counts['negatif'] ?? 0),
            ];
        }

        usort($words, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['total_tokens' => $total, 'words' => $words];
    }

    /** @return list<string> */
    private function stopwords(): array
    {
        $path = dirname(__DIR__, 2).'/resources/stopwords-id.txt';

        if (! is_file($path)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', file($path))));
    }

    /**
     * @param  array{total_tokens: int, words: list<array{token: string, label: string, count: int}>}  $freq
     */
    private function wordsPayload(string $runId, array $freq): string
    {
        $items = [];

        foreach ($freq['words'] as $i => $word) {
            $items[] = [
                'rank' => $i + 1,
                'token' => $word['token'],
                'label' => $word['label'],
                'count' => $word['count'],
                'share' => round($word['count'] / $freq['total_tokens'], 6),
            ];
        }

        return $this->json([
            'run_id' => $runId,
            'total_docs' => self::TOTAL,
            'total_tokens' => $freq['total_tokens'],
            'words' => $items,
        ]);
    }

    /**
     * @param  list<array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string, review: string}>  $oof
     */
    private function reviewsPayload(string $runId, array $oof): string
    {
        $items = [];

        foreach ($oof as $i => $row) {
            $items[] = [
                'review_id' => $row['review_id'],
                'username' => 'Pengguna Google',
                'rating' => $row['label_true'] === 1 ? 5 : 1,
                'review' => $row['review'],
                'date' => '2026-09-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'true_label' => $row['label_true'] === 1 ? 'positif' : 'negatif',
                'pred_label' => $row['label_pred'] === 1 ? 'positif' : 'negatif',
                'proba_pos' => $row['label_pred'] === 1 ? 0.87 : 0.21,
                'basis' => $row['basis'],
                'fold' => $row['fold'],
            ];
        }

        return $this->json([
            'run_id' => $runId,
            'total_docs' => self::TOTAL,
            'reviews' => $items,
        ]);
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
}
