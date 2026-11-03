<?php

namespace App\Support;

/**
 * Evaluasi K-Fold untuk grid alpha x K sesuai PRD.
 *
 * Setiap konfigurasi menghasilkan metrik agregat (mean/std antar fold), rincian
 * metrik per fold, dan prediksi out-of-fold konfigurasi terbaik.
 */
final class KFoldEvaluator
{
    public const ALPHAS = [0.01, 0.1, 0.5, 1.0, 1.5, 2.0];

    public const FOLDS = [5, 10];

    public function __construct(private readonly int $seed = 42) {}

    /**
     * @param  list<array{tokens: list<string>, label_true: int, review_id?: string}>  $rows
     * @return array{
     *     configs: list<array<string, mixed>>,
     *     best: array<string, mixed>|null,
     *     fold_plans: array<string, array<string, mixed>>,
     *     oof: array<string, array{label_pred: int, proba_pos: float, fold: int}>
     * }
     */
    public function evaluate(array $rows): array
    {
        $labels = array_map(fn (array $row): int => (int) $row['label_true'], $rows);
        $documents = array_map(fn (array $row): array => $row['tokens'], $rows);
        $configs = [];
        $foldPlans = [];

        foreach (self::FOLDS as $k) {
            $split = (new StratifiedKFold($this->seed))->split($labels, $k);
            $foldPlans[(string) $k] = $split['plan'];

            foreach (self::ALPHAS as $alpha) {
                $configs[] = $this->evaluateConfig($rows, $documents, $labels, $split, $k, $alpha);
            }
        }

        $best = (new BestConfigSelector)->select($configs);
        $oof = [];

        if ($best !== null) {
            foreach ($configs as $config) {
                if ((float) $config['alpha'] === (float) $best['alpha'] && (int) $config['k'] === (int) $best['k']) {
                    $oof = $config['oof'];
                    break;
                }
            }
        }

        return [
            'configs' => $configs,
            'best' => $best,
            'fold_plans' => $foldPlans,
            'oof' => $oof,
        ];
    }

    /**
     * @param  list<array{tokens: list<string>, label_true: int, review_id?: string}>  $rows
     * @param  list<list<string>>  $documents
     * @param  list<int>  $labels
     * @param  array<string, mixed>  $split
     * @return array<string, mixed>
     */
    private function evaluateConfig(
        array $rows,
        array $documents,
        array $labels,
        array $split,
        int $k,
        float $alpha,
    ): array {
        $perFold = [];
        $oof = [];

        foreach ($split['iterations'] as $iteration) {
            $testIndices = $iteration['test_indices'];

            if ($testIndices === []) {
                continue;
            }

            $trainIndices = $iteration['train_indices'];
            $trainDocuments = array_map(fn (int $index): array => $documents[$index], $trainIndices);
            $trainLabels = array_map(fn (int $index): int => $labels[$index], $trainIndices);
            $model = new MultinomialNaiveBayes;
            $model->train($trainDocuments, $trainLabels, $alpha);

            $testDocuments = array_map(fn (int $index): array => $documents[$index], $testIndices);
            $predictions = $model->predictAll($testDocuments);
            $foldPredictions = [];

            foreach ($testIndices as $position => $index) {
                $fold = (int) ($split['assignments'][$index] ?? 1);
                $prediction = $predictions[$position];
                $foldPredictions[$fold][] = [
                    'index' => $index,
                    'prediction' => $prediction,
                ];
                $reviewId = (string) ($rows[$index]['review_id'] ?? $index);
                $oof[$reviewId] = [
                    'label_pred' => $prediction['label'],
                    'proba_pos' => $prediction['proba_pos'],
                    'fold' => $fold,
                ];
            }

            ksort($foldPredictions);

            foreach ($foldPredictions as $fold => $items) {
                $truth = array_map(fn (array $item): int => $labels[$item['index']], $items);
                $predicted = array_map(fn (array $item): int => $item['prediction']['label'], $items);
                $metrics = $this->metrics($truth, $predicted);

                $perFold[] = [
                    'fold' => $fold,
                    'iteration' => (int) $iteration['iteration'],
                    'test_folds' => $iteration['test_folds'],
                    'train_size' => (int) $iteration['train_size'],
                    'test_size' => count($items),
                    'accuracy' => $metrics['accuracy'],
                    'positive' => $this->withStd($metrics['positive']),
                    'negative' => $this->withStd($metrics['negative']),
                    'macro' => $this->withStd($metrics['macro']),
                ];
            }
        }

        usort($perFold, fn (array $a, array $b): int => $a['fold'] <=> $b['fold']);

        return [
            'k' => $k,
            'alpha' => $alpha,
            'folds' => $k,
            'effective_folds' => (int) $split['fold_count'],
            'test_folds_per_iteration' => (int) $split['plan']['test_folds_per_iteration'],
            'iterations' => count($split['iterations']),
            'metrics' => $this->aggregate($perFold),
            'per_fold' => $perFold,
            'oof' => $oof,
        ];
    }

    /**
     * @param  list<int>  $truth
     * @param  list<int>  $predicted
     * @return array{
     *     accuracy: float,
     *     positive: array{precision: float, recall: float, f1: float},
     *     negative: array{precision: float, recall: float, f1: float},
     *     macro: array{precision: float, recall: float, f1: float}
     * }
     */
    private function metrics(array $truth, array $predicted): array
    {
        $tp = $tn = $fp = $fn = 0;

        foreach ($truth as $index => $actual) {
            $guess = $predicted[$index] ?? 0;

            if ($actual === 1 && $guess === 1) {
                $tp++;
            } elseif ($actual === 0 && $guess === 0) {
                $tn++;
            } elseif ($actual === 0 && $guess === 1) {
                $fp++;
            } else {
                $fn++;
            }
        }

        $total = count($truth);
        $positive = $this->classMetrics($tp, $fp, $fn);
        $negative = $this->classMetrics($tn, $fn, $fp);

        return [
            'accuracy' => $total > 0 ? round(($tp + $tn) / $total, 4) : 0.0,
            'positive' => $positive,
            'negative' => $negative,
            'macro' => [
                'precision' => round(($positive['precision'] + $negative['precision']) / 2, 4),
                'recall' => round(($positive['recall'] + $negative['recall']) / 2, 4),
                'f1' => round(($positive['f1'] + $negative['f1']) / 2, 4),
            ],
        ];
    }

    /**
     * @return array{precision: float, recall: float, f1: float}
     */
    private function classMetrics(int $truePositive, int $falsePositive, int $falseNegative): array
    {
        $precision = $truePositive + $falsePositive > 0
            ? $truePositive / ($truePositive + $falsePositive)
            : 0.0;
        $recall = $truePositive + $falseNegative > 0
            ? $truePositive / ($truePositive + $falseNegative)
            : 0.0;
        $f1 = $precision + $recall > 0
            ? 2 * $precision * $recall / ($precision + $recall)
            : 0.0;

        return [
            'precision' => round($precision, 4),
            'recall' => round($recall, 4),
            'f1' => round($f1, 4),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $perFold
     * @return array<string, mixed>
     */
    private function aggregate(array $perFold): array
    {
        return [
            'accuracy' => $this->stat(array_column($perFold, 'accuracy')),
            'positive' => [
                'precision' => $this->stat(array_map(fn (array $row): float => $row['positive']['precision']['mean'], $perFold)),
                'recall' => $this->stat(array_map(fn (array $row): float => $row['positive']['recall']['mean'], $perFold)),
                'f1' => $this->stat(array_map(fn (array $row): float => $row['positive']['f1']['mean'], $perFold)),
            ],
            'negative' => [
                'precision' => $this->stat(array_map(fn (array $row): float => $row['negative']['precision']['mean'], $perFold)),
                'recall' => $this->stat(array_map(fn (array $row): float => $row['negative']['recall']['mean'], $perFold)),
                'f1' => $this->stat(array_map(fn (array $row): float => $row['negative']['f1']['mean'], $perFold)),
            ],
            'macro' => [
                'precision' => $this->stat(array_map(fn (array $row): float => $row['macro']['precision']['mean'], $perFold)),
                'recall' => $this->stat(array_map(fn (array $row): float => $row['macro']['recall']['mean'], $perFold)),
                'f1' => $this->stat(array_map(fn (array $row): float => $row['macro']['f1']['mean'], $perFold)),
            ],
        ];
    }

    /**
     * @param  array<string, float>  $metrics
     * @return array<string, array{mean: float, std: float}>
     */
    private function withStd(array $metrics): array
    {
        $result = [];

        foreach ($metrics as $name => $value) {
            $result[$name] = ['mean' => (float) $value, 'std' => 0.0];
        }

        return $result;
    }

    /**
     * @param  list<float>  $values
     * @return array{mean: float, std: float}
     */
    private function stat(array $values): array
    {
        $count = count($values);

        if ($count === 0) {
            return ['mean' => 0.0, 'std' => 0.0];
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return [
            'mean' => round((float) $mean, 4),
            'std' => round(sqrt($variance / $count), 4),
        ];
    }
}
