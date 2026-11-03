<?php

namespace App\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pembagian data K-Fold secara stratifikasi dan deterministik.
 *
 * K=5 memakai 1 fold uji per iterasi. K=10 memakai 2 fold uji per iterasi
 * (pasangan fold) sehingga setiap iterasi melatih 8 fold dan menguji 2 fold,
 * sesuai skema yang diminta. Setiap sampel tetap menjadi data uji tepat sekali.
 */
final class StratifiedKFold
{
    public function __construct(private readonly int $seed = 42) {}

    /**
     * @param  array<int, int>  $labels  label per indeks sampel (0/1)
     * @return array{
     *     fold_count: int,
     *     assignments: array<int, int>,
     *     folds: array<int, array<int, int>>,
     *     iterations: array<int, array{
     *         iteration: int,
     *         train_folds: array<int, int>,
     *         test_folds: array<int, int>,
     *         train_indices: array<int, int>,
     *         test_indices: array<int, int>,
     *         train_size: int,
     *         test_size: int
     *     }>,
     *     plan: array{
     *         k: int,
     *         fold_count: int,
     *         test_folds_per_iteration: int,
     *         iterations: array<int, array{
     *             iteration: int,
     *             train_folds: array<int, int>,
     *             test_folds: array<int, int>,
     *             train_size: int,
     *             test_size: int
     *         }>
     *     }
     * }
     */
    public function split(array $labels, int $k): array
    {
        $total = count($labels);

        if ($total === 0) {
            return [
                'fold_count' => 0,
                'assignments' => [],
                'folds' => [],
                'iterations' => [],
                'plan' => [
                    'k' => $k,
                    'fold_count' => 0,
                    'test_folds_per_iteration' => 0,
                    'iterations_count' => 0,
                    'folds' => [],
                    'iterations' => [],
                ],
            ];
        }

        $foldCount = min($k, $total);
        $minClassCount = $this->minClassCount($labels);

        if ($minClassCount >= 2) {
            $foldCount = min($foldCount, $minClassCount);
        }

        if ($foldCount < 2) {
            $indices = range(0, $total - 1);
            $iteration = [
                'iteration' => 1,
                'train_folds' => [],
                'test_folds' => [1],
                'train_indices' => [],
                'test_indices' => $indices,
                'train_size' => 0,
                'test_size' => $total,
            ];

            return [
                'fold_count' => 1,
                'assignments' => array_fill(0, $total, 1),
                'folds' => [1 => $indices],
                'iterations' => [$iteration],
                'plan' => [
                    'k' => $k,
                    'fold_count' => 1,
                    'test_folds_per_iteration' => 1,
                    'iterations_count' => 1,
                    'folds' => $this->foldPlanRows([1 => $indices], $labels),
                    'iterations' => [$this->planIteration($iteration)],
                ],
            ];
        }

        $assignments = array_fill(0, $total, 1);
        $folds = array_fill_keys(range(1, $foldCount), []);
        $randomizer = new Randomizer(new Mt19937($this->seed));
        $byClass = [];

        foreach ($labels as $index => $label) {
            $byClass[(int) $label][] = $index;
        }

        ksort($byClass);

        foreach ($byClass as $indices) {
            $shuffled = $randomizer->shuffleArray($indices);

            foreach ($shuffled as $position => $index) {
                $fold = ($position % $foldCount) + 1;
                $folds[$fold][] = $index;
                $assignments[$index] = $fold;
            }
        }

        $testFoldsPerIteration = $k >= 10 ? 2 : 1;
        $testFoldsPerIteration = max(1, min($testFoldsPerIteration, $foldCount));
        $iterations = [];
        $iterationNumber = 1;

        for ($start = 1; $start <= $foldCount; $start += $testFoldsPerIteration) {
            $testFolds = range($start, min($start + $testFoldsPerIteration - 1, $foldCount));
            $trainFolds = array_values(array_diff(range(1, $foldCount), $testFolds));
            $testIndices = $this->mergeFolds($folds, $testFolds);

            if ($testIndices === []) {
                continue;
            }

            $trainIndices = $this->mergeFolds($folds, $trainFolds);
            $iteration = [
                'iteration' => $iterationNumber,
                'train_folds' => $trainFolds,
                'test_folds' => $testFolds,
                'train_indices' => $trainIndices,
                'test_indices' => $testIndices,
                'train_size' => count($trainIndices),
                'test_size' => count($testIndices),
            ];

            $iterations[] = $iteration;
            $iterationNumber++;
        }

        return [
            'fold_count' => $foldCount,
            'assignments' => $assignments,
            'folds' => $folds,
            'iterations' => $iterations,
            'plan' => [
                'k' => $k,
                'fold_count' => $foldCount,
                'test_folds_per_iteration' => $testFoldsPerIteration,
                'iterations_count' => count($iterations),
                'folds' => $this->foldPlanRows($folds, $labels),
                'iterations' => array_map(fn (array $iteration): array => $this->planIteration($iteration), $iterations),
            ],
        ];
    }

    /**
     * @param  array<int, int>  $labels
     */
    private function minClassCount(array $labels): int
    {
        $counts = [];

        foreach ($labels as $label) {
            $counts[(int) $label] = ($counts[(int) $label] ?? 0) + 1;
        }

        return $counts === [] ? 0 : min($counts);
    }

    /**
     * @param  array<int, array<int, int>>  $folds
     * @param  array<int, int>  $labels
     * @return list<array{fold: int, size: int, positive: int, negative: int}>
     */
    private function foldPlanRows(array $folds, array $labels): array
    {
        $rows = [];
        ksort($folds);

        foreach ($folds as $fold => $indices) {
            $positive = 0;
            $negative = 0;

            foreach ($indices as $index) {
                if ((int) ($labels[$index] ?? 0) === 1) {
                    $positive++;
                } else {
                    $negative++;
                }
            }

            $rows[] = [
                'fold' => (int) $fold,
                'size' => count($indices),
                'positive' => $positive,
                'negative' => $negative,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, int>>  $folds
     * @param  array<int, int>  $foldNumbers
     * @return array<int, int>
     */
    private function mergeFolds(array $folds, array $foldNumbers): array
    {
        $indices = [];

        foreach ($foldNumbers as $fold) {
            foreach ($folds[$fold] ?? [] as $index) {
                $indices[] = $index;
            }
        }

        sort($indices);

        return $indices;
    }

    /**
     * @param  array<string, mixed>  $iteration
     * @return array<string, mixed>
     */
    private function planIteration(array $iteration): array
    {
        return [
            'iteration' => $iteration['iteration'],
            'train_folds' => $iteration['train_folds'],
            'test_folds' => $iteration['test_folds'],
            'train_size' => $iteration['train_size'],
            'test_size' => $iteration['test_size'],
        ];
    }
}
