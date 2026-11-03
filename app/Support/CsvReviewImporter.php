<?php

namespace App\Support;

use RuntimeException;

/**
 * Impor ulasan CSV (username, rating, review, date) menjadi artefak nyata.
 *
 * Aturan label memakai proxy rating sesuai keputusan pemilik proyek: rating 4-5
 * = positif (1), rating 1-2 = negatif (0), rating 3 = netral -> dikeluarkan
 * sebagai "unclear". Ini penyimpangan sadar dari BL-14 (label manual) yang
 * dicatat di run_meta.json. Tokenisasi berbagi konvensi dengan PredictService
 * dan menambah penyaringan stopword (resources/stopwords-id.txt). Evaluasi
 * memakai K-Fold stratifikasi + Multinomial Naive Bayes (TF-IDF per fold).
 */
class CsvReviewImporter
{
    public const NEUTRAL_RATING = 3;

    /** @var array<int, string> stopword dalam huruf kecil */
    private array $stopwords;

    public function __construct()
    {
        $this->stopwords = $this->loadStopwords();
    }

    /**
     * Impor CSV dan menulis seluruh artefak ke direktori artifacts.
     *
     * @return array<string, mixed> ringkasan hasil untuk output CLI/tests
     */
    public function import(string $path, int $topWords = 200): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas CSV tidak ditemukan: {$path}");
        }

        $rows = $this->read($path);
        $processed = $this->process($rows);
        $artifacts = $this->artifacts($processed, $path, $topWords);

        $target = (string) config('artifacts.path');

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            throw new RuntimeException("Tidak dapat membuat direktori artefak: {$target}");
        }

        foreach ($artifacts as $filename => $content) {
            file_put_contents($target.DIRECTORY_SEPARATOR.$filename, $content);
        }

        return $processed['summary'];
    }

    /**
     * Tokenisasi konsisten dengan PredictService + stopword & panjang >= 3.
     *
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-zA-Z]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $this->stopwords, true),
        ));

        return $tokens;
    }

    /**
     * @return list<array{username: string, rating: int, review: string, date: string, index: int}>
     */
    private function read(string $path): array
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException("Tidak dapat membuka CSV: {$path}");
        }

        $rows = [];
        $index = 0;

        try {
            while (($line = fgetcsv($stream)) !== false) {
                $index++;

                if (count($line) < 2) {
                    continue;
                }

                $rating = $this->normalizeRating($line[1] ?? null);

                if ($rating === null) {
                    continue;
                }

                $rows[] = [
                    'index' => $index,
                    'username' => (string) ($line[0] ?? ''),
                    'rating' => $rating,
                    'review' => (string) ($line[2] ?? ''),
                    'date' => (string) ($line[3] ?? ''),
                ];
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            throw new RuntimeException("CSV tidak memuat baris ulasan yang valid: {$path}");
        }

        return $rows;
    }

    private function normalizeRating(?string $value): ?int
    {
        if ($value === null || ! is_numeric(trim($value))) {
            return null;
        }

        $rating = (int) round((float) trim($value));

        return $rating >= 1 && $rating <= 5 ? $rating : null;
    }

    /**
     * @param  list<array{username: string, rating: int, review: string, date: string, index: int}>  $rows
     * @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function process(array $rows): array
    {
        $included = [];
        $excluded = [
            'duplicate' => 0,
            'empty' => 0,
            'unclear' => 0,
            'out_of_range' => 0,
            'empty_after_preprocessing' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['rating'] === self::NEUTRAL_RATING) {
                $excluded['unclear']++;

                continue;
            }

            $tokens = $this->tokenize($row['review']);

            if ($tokens === []) {
                $excluded['empty_after_preprocessing']++;

                continue;
            }

            $included[] = [
                'review_id' => 'r'.str_pad((string) $row['index'], 6, '0', STR_PAD_LEFT),
                'username' => $row['username'],
                'rating' => $row['rating'],
                'review' => trim($row['review']),
                'date' => $row['date'],
                'label_true' => $row['rating'] >= 4 ? 1 : 0,
                'tokens' => $tokens,
            ];
        }

        $positive = 0;
        $negative = 0;

        foreach ($included as $row) {
            $row['label_true'] === 1 ? $positive++ : $negative++;
        }

        return [
            'rows' => $included,
            'summary' => [
                'raw' => count($rows),
                'excluded' => $excluded,
                'included' => count($included),
                'class_distribution' => ['positif' => $positive, 'negatif' => $negative],
                'label_agreement' => null,
            ],
        ];
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, summary: array<string, mixed>}  $processed
     * @return array<string, string>
     */
    private function artifacts(array $processed, string $path, int $topWords): array
    {
        $runId = substr(hash('sha256', (string) file_get_contents($path)), 0, 6);
        $datasetHash = hash('sha256', (string) file_get_contents($path));
        $included = $processed['rows'];
        $summary = $processed['summary'];

        $evaluation = (new KFoldEvaluator)->evaluate($included);
        $best = $evaluation['best'] ?? ['alpha' => 1.0, 'k' => 10];
        $oof = $this->oofRows($included, $evaluation['oof']);
        $words = $this->wordFrequencies($included);

        return [
            'run_meta.json' => $this->json([
                'run_id' => $runId,
                'seed' => 42,
                'best_alpha' => (float) $best['alpha'],
                'best_k' => (int) $best['k'],
                'dataset_sha256' => $datasetHash,
                'generated_at' => now()->toIso8601String(),
                'demo' => false,
                'source' => 'csv-import',
                'kfold' => [
                    'strategy' => 'stratified',
                    'folds' => KFoldEvaluator::FOLDS,
                    'k10_test_folds' => 2,
                    'seed' => 42,
                ],
                'note' => 'Label true = proxy rating (4-5 positif, 1-2 negatif, 3 netral dieksklusi; penyimpangan BL-14 atas keputusan pemilik proyek). label_pred dari Multinomial Naive Bayes + TF-IDF yang dilatih per fold K-Fold stratifikasi.',
            ]),
            'data_summary.json' => $this->json($summary),
            'results.json' => $this->json($this->results($runId, $evaluation, $summary['class_distribution'])),
            'oof_predictions.csv' => $this->oofCsv($oof),
            'words.json' => $this->json($this->wordsPayload($runId, $words, $topWords, count($included))),
            'reviews.json' => $this->json($this->reviewsPayload($runId, $included, $oof)),
        ];
    }

    /**
     * Daftar ulasan berlabel + text + rating untuk halaman Ulasan Pengguna.
     *
     * @param  list<array<string, mixed>>  $included
     * @param  list<array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int}>  $oof
     * @return array<string, mixed>
     */
    private function reviewsPayload(string $runId, array $included, array $oof): array
    {
        $predictions = [];

        foreach ($oof as $row) {
            $predictions[$row['review_id']] = $row;
        }

        $items = [];

        foreach ($included as $row) {
            $pred = $predictions[$row['review_id']] ?? null;

            if ($pred === null) {
                continue;
            }

            $items[] = [
                'review_id' => $row['review_id'],
                'username' => $row['username'],
                'rating' => (int) $row['rating'],
                'review' => (string) $row['review'],
                'date' => (string) $row['date'],
                'true_label' => ((int) $row['label_true']) === 1 ? 'positif' : 'negatif',
                'pred_label' => ((int) $pred['label_pred']) === 1 ? 'positif' : 'negatif',
                'proba_pos' => (float) $pred['proba_pos'],
                'basis' => $pred['basis'] === 'teks' ? 'teks' : 'rating',
                'fold' => (int) $pred['fold'],
            ];
        }

        return [
            'run_id' => $runId,
            'total_docs' => count($items),
            'reviews' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $included
     * @param  array<string, array{label_pred: int, proba_pos: float, fold: int}>  $predictions
     * @return list<array{review_id: string, label_true: int, label_pred: int, proba_pos: float, fold: int, basis: string}>
     */
    private function oofRows(array $included, array $predictions): array
    {
        $rows = [];

        foreach ($included as $row) {
            $reviewId = (string) $row['review_id'];
            $prediction = $predictions[$reviewId] ?? null;

            $rows[] = [
                'review_id' => $reviewId,
                'label_true' => (int) $row['label_true'],
                'label_pred' => $prediction === null ? (int) $row['label_true'] : (int) $prediction['label_pred'],
                'proba_pos' => $prediction === null ? 0.5 : (float) $prediction['proba_pos'],
                'basis' => $prediction === null ? 'fallback' : 'model',
                'fold' => $prediction === null ? 1 : (int) $prediction['fold'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array{positif: int, negatif: int}  $distribution
     * @return array<string, mixed>
     */
    private function results(string $runId, array $evaluation, array $distribution): array
    {
        $total = $distribution['positif'] + $distribution['negatif'];
        $configs = [];

        foreach ($evaluation['configs'] as $config) {
            $configs[] = [
                'k' => (int) $config['k'],
                'alpha' => (float) $config['alpha'],
                'folds' => (int) $config['folds'],
                'effective_folds' => (int) $config['effective_folds'],
                'test_folds_per_iteration' => (int) $config['test_folds_per_iteration'],
                'iterations' => (int) $config['iterations'],
                'metrics' => $config['metrics'],
                'per_fold' => $config['per_fold'],
            ];
        }

        $best = $evaluation['best'] ?? ['alpha' => 1.0, 'k' => 10];

        return [
            'run_id' => $runId,
            'best' => ['alpha' => (float) $best['alpha'], 'k' => (int) $best['k']],
            'baseline_majority_accuracy' => $total > 0 ? round(max($distribution['positif'], $distribution['negatif']) / $total, 6) : 0.0,
            'fold_plans' => $evaluation['fold_plans'],
            'configs' => $configs,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $oof
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
     * @param  list<array<string, mixed>>  $included
     * @return array<string, array{count: int, positive: int, negative: int}>
     */
    private function wordFrequencies(array $included): array
    {
        $words = [];

        foreach ($included as $row) {
            $label = (int) $row['label_true'];

            foreach ($row['tokens'] as $token) {
                $words[$token] ??= ['count' => 0, 'positive' => 0, 'negative' => 0];

                $words[$token]['count']++;
                $label === 1 ? $words[$token]['positive']++ : $words[$token]['negative']++;
            }
        }

        return $words;
    }

    /**
     * @param  array<string, array{count: int, positive: int, negative: int}>  $words
     * @return array<string, mixed>
     */
    private function wordsPayload(string $runId, array $words, int $topWords, int $totalDocs): array
    {
        $totalTokens = array_sum(array_column($words, 'count'));

        uasort($words, fn (array $a, array $b): int => [$b['count'], $a['positive']] <=> [$a['count'], $b['positive']]);

        $items = [];

        foreach (array_slice($words, 0, $topWords, true) as $token => $word) {
            $items[] = [
                'token' => $token,
                'count' => $word['count'],
                'label' => $word['positive'] >= $word['negative'] ? 'positif' : 'negatif',
                'share' => $totalTokens > 0 ? round($word['count'] / $totalTokens, 6) : 0.0,
            ];
        }

        return [
            'run_id' => $runId,
            'total_docs' => $totalDocs,
            'total_tokens' => $totalTokens,
            'words' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @return array<int, string>
     */
    private function loadStopwords(): array
    {
        $path = resource_path('stopwords-id.txt');

        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $stopwords = array_map(
            fn (string $line): string => mb_strtolower(trim($line)),
            array_values(array_filter($lines)),
        );

        return array_values(array_filter($stopwords, fn (string $line): bool => $line !== ''));
    }
}
