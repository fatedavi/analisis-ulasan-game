<?php

namespace App\Support;

use RuntimeException;

/**
 * Membaca artefak hasil pipeline ML dari disk (BL-53).
 *
 * Artefak boleh absen atau rusak; dalam kasus itu metode mengembalikan null
 * dan alasan tercatat lewat errors(). UI/API tidak pernah memakai angka
 * hardcode sebagai pengganti (AC-05, BL-60).
 */
class ArtifactRepository
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * Jalur lengkap ke sebuah berkas artefak.
     */
    public function path(string $file): string
    {
        return rtrim(config('artifacts.path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;
    }

    public function has(string $file): bool
    {
        return is_file($this->path($file));
    }

    /**
     * @return array<string, string> daftar berkas yang absen/rusak dan alasannya
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Status ringkas untuk /healthz.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $meta = $this->meta();

        return [
            'ok' => $this->hasAll(),  // deprecated: lihat present
            'present' => $this->presence(),
            'demo' => (bool) ($meta['demo'] ?? false),
            'run_id' => $meta['run_id'] ?? null,
            'errors' => $this->errors,
        ];
    }

    /**
     * Frequensi kata untuk word cloud (dari words.json).
     *
     * @return array<string, mixed>|null
     */
    public function words(): ?array
    {
        $words = $this->json('words.json');

        if ($words === null) {
            return null;
        }

        if (! isset($words['words']) || ! is_array($words['words'])) {
            $this->errors['words.json'] = 'invalid';

            return null;
        }

        return $words;
    }

    /**
     * Ringkasan dashboard (FR-W02) dari prediksi OOF + run_meta.
     *
     * @return array<string, mixed>|null
     */
    public function summary(): ?array
    {
        $meta = $this->meta();

        if ($meta === null) {
            return null;
        }

        $oof = $this->oofPredictions();

        if ($oof === null) {
            $this->errors['oof_predictions.csv'] = 'missing';

            return null;
        }

        $total = count($oof);
        $positive = 0;

        foreach ($oof as $row) {
            if ($row['label_pred'] === 1) {
                $positive++;
            }
        }

        $negative = $total - $positive;

        return [
            'run_id' => $meta['run_id'] ?? null,
            'total' => $total,
            'positive' => $positive,
            'negative' => $negative,
            'sentiment_score' => $this->sentimentScore($positive, $total),
            'source' => 'oof_predictions',
            'demo' => (bool) ($meta['demo'] ?? false),
            'generated_at' => $meta['generated_at'] ?? null,
        ];
    }

    /**
     * Evaluasi model (FR-W04–W07) untuk konfigurasi terbaik.
     *
     * @return array<string, mixed>|null
     */
    public function evaluation(): ?array
    {
        $meta = $this->meta();

        if ($meta === null) {
            return null;
        }

        $results = $this->results();

        if ($results === null) {
            return null;
        }

        $best = $results['best'] ?? null;

        if (! is_array($best)) {
            $this->errors['results.json'] = 'invalid';

            return null;
        }

        $metrics = $this->bestConfigMetrics($results, $best);

        if ($metrics === null) {
            return null;
        }

        $oof = $this->oofPredictions();

        if ($oof === null) {
            $this->errors['oof_predictions.csv'] = 'missing';

            return null;
        }

        $confusion = $this->confusionMatrix($oof);

        return [
            'run_id' => $meta['run_id'] ?? null,
            'demo' => (bool) ($meta['demo'] ?? false),
            'best' => ['alpha' => (float) $best['alpha'], 'k' => (int) $best['k']],
            'confusion_matrix' => [
                'tn' => $confusion['tn'],
                'fp' => $confusion['fp'],
                'fn' => $confusion['fn'],
                'tp' => $confusion['tp'],
                'orientation' => 'rows=actual, cols=predicted',
            ],
            'metrics_mean_over_folds' => $metrics,
            'baseline_majority_accuracy' => $this->baselineMajorityAccuracy($oof),
            'grid' => $this->grid($results),
        ];
    }

    /**
     * Daftar ulasan terfilter + terpaginasi untuk halaman Ulasan Pengguna.
     *
     * @return array<string, mixed>|null
     */
    public function reviews(
        ?string $label = null,
        ?int $rating = null,
        ?string $q = null,
        int $page = 1,
        int $perPage = 12,
    ): ?array {
        $payload = $this->json('reviews.json');

        if ($payload === null) {
            return null;
        }

        if (! isset($payload['reviews']) || ! is_array($payload['reviews'])) {
            $this->errors['reviews.json'] = 'invalid';

            return null;
        }

        $rows = $this->filterReviews($payload['reviews'], $label, $rating, $q);
        $total = count($rows);
        $positif = 0;
        $negatif = 0;
        $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($rows as $row) {
            $row['pred_label'] === 'positif' ? $positif++ : $negatif++;

            $stars = (int) ($row['rating'] ?? 0);

            if (isset($ratingCounts[$stars])) {
                $ratingCounts[$stars]++;
            }
        }

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $data = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return [
            'run_id' => $payload['run_id'] ?? null,
            'total' => $total,
            'positif' => $positif,
            'negatif' => $negatif,
            'rating_distribution' => $ratingCounts,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'label' => $label,
            'rating' => $rating,
            'q' => $q,
            'data' => array_values($data),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterReviews(array $rows, ?string $label, ?int $rating, ?string $q): array
    {
        return array_values(array_filter(
            $rows,
            function (array $row) use ($label, $rating, $q): bool {
                if ($label !== null && ($row['pred_label'] ?? null) !== $label) {
                    return false;
                }

                if ($rating !== null && (int) ($row['rating'] ?? 0) !== $rating) {
                    return false;
                }

                if ($q !== '' && $q !== null) {
                    $review = (string) ($row['review'] ?? '');

                    if (mb_stripos($review, $q) === false) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->json('run_meta.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function results(): ?array
    {
        return $this->json('results.json');
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function oofPredictions(): ?array
    {
        $raw = $this->file('oof_predictions.csv');

        if ($raw === null) {
            $this->errors['oof_predictions.csv'] = 'missing';

            return null;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $rows = [];
        $header = null;

        try {
            while (($line = fgetcsv($stream)) !== false) {
                if ($header === null) {
                    $header = $this->parseHeader($line);

                    if ($header === null) {
                        throw new RuntimeException('header tidak dikenal');
                    }

                    continue;
                }

                $mapped = [];

                foreach ($header as $column => $index) {
                    $mapped[$column] = $line[$index] ?? null;
                }

                if ($mapped['label_true'] === null || $mapped['label_pred'] === null) {
                    continue;
                }

                $rows[] = [
                    'review_id' => (string) ($mapped['review_id'] ?? ''),
                    'label_true' => (int) $mapped['label_true'],
                    'label_pred' => (int) $mapped['label_pred'],
                    'proba_pos' => $mapped['proba_pos'] === null ? null : (float) $mapped['proba_pos'],
                    'fold' => $mapped['fold'] === null ? null : (int) $mapped['fold'],
                ];
            }
        } catch (RuntimeException $e) {
            $this->errors['oof_predictions.csv'] = 'invalid';

            return null;
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            $this->errors['oof_predictions.csv'] = 'invalid';

            return null;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $line
     * @return array<string, int>|null
     */
    private function parseHeader(array $line): ?array
    {
        $known = ['review_id', 'label_true', 'label_pred', 'proba_pos', 'fold'];
        $lowered = array_map(fn (mixed $value): string => mb_strtolower(trim((string) $value)), $line);

        if (! in_array('label_true', $lowered, true) || ! in_array('label_pred', $lowered, true)) {
            return null;
        }

        $map = [];

        foreach ($known as $column) {
            $index = array_search($column, $lowered, true);

            if ($index !== false) {
                $map[$column] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oof
     * @return array{tn: int, fp: int, fn: int, tp: int}
     */
    private function confusionMatrix(array $oof): array
    {
        $confusion = ['tn' => 0, 'fp' => 0, 'fn' => 0, 'tp' => 0];

        foreach ($oof as $row) {
            $true = $row['label_true'];
            $pred = $row['label_pred'];

            if ($true === 0 && $pred === 0) {
                $confusion['tn']++;
            } elseif ($true === 0 && $pred === 1) {
                $confusion['fp']++;
            } elseif ($true === 1 && $pred === 0) {
                $confusion['fn']++;
            } else {
                $confusion['tp']++;
            }
        }

        return $confusion;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oof
     */
    private function baselineMajorityAccuracy(array $oof): float
    {
        $positive = 0;
        $total = count($oof);

        foreach ($oof as $row) {
            if ($row['label_pred'] === 1) {
                $positive++;
            }
        }

        $negative = $total - $positive;

        return $total === 0 ? 0.0 : round(max($positive, $negative) / $total, 6);
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $best
     * @return array<string, mixed>|null
     */
    private function bestConfigMetrics(array $results, array $best): ?array
    {
        $configs = $results['configs'] ?? [];

        if (! is_array($configs)) {
            $this->errors['results.json'] = 'invalid';

            return null;
        }

        foreach ($configs as $config) {
            if ((float) $config['alpha'] === (float) $best['alpha'] && (int) $config['k'] === (int) $best['k']) {
                return $config['metrics'];
            }
        }

        $this->errors['results.json'] = 'best_config_missing';

        return null;
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<int, array<string, mixed>>
     */
    private function grid(array $results): array
    {
        $configs = $results['configs'] ?? [];

        if (! is_array($configs)) {
            return [];
        }

        $grid = [];

        foreach ($configs as $config) {
            $grid[] = [
                'k' => (int) ($config['k'] ?? 0),
                'alpha' => (float) ($config['alpha'] ?? 0),
                'macro_f1' => (float) data_get($config, 'metrics.macro.f1.mean'),
                'accuracy' => (float) data_get($config, 'metrics.accuracy.mean'),
            ];
        }

        usort($grid, fn (array $a, array $b): int => [$a['k'], $a['alpha']] <=> [$b['k'], $b['alpha']]);

        return $grid;
    }

    private function file(string $file): ?string
    {
        if (! $this->has($file)) {
            return null;
        }

        $content = file_get_contents($this->path($file));

        return $content === false ? null : $content;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(string $file): ?array
    {
        $raw = $this->file($file);

        if ($raw === null) {
            $this->errors[$file] = 'missing';

            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->errors[$file] = 'invalid';

            return null;
        }

        return $decoded;
    }

    private function sentimentScore(int $positive, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        return (int) round(100 * $positive / $total);
    }

    /**
     * @return array<string, bool>
     */
    private function presence(): array
    {
        $present = [];

        foreach (config('artifacts') as $key => $value) {
            if ($key === 'path' || ! is_string($value)) {
                continue;
            }

            $present[$value] = $this->has($value);
        }

        return $present;
    }

    private function hasAll(): bool
    {
        return $this->has(config('artifacts.run_meta'))
            && $this->has(config('artifacts.results'))
            && $this->has(config('artifacts.oof_predictions'))
            && $this->has(config('artifacts.data_summary'));
    }
}
