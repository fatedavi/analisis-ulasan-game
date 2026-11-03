<?php

namespace App\Support;

use function count;
use function log;
use function max;
use function sqrt;

/**
 * Multinomial Naive Bayes sederhana dengan fitur TF-IDF.
 *
 * Vectorizer (vocabulary + IDF) di-fit hanya dari dokumen latih yang diberikan
 * ke train(), sehingga evaluasi K-Fold tidak bocor dari data uji.
 */
final class MultinomialNaiveBayes
{
    /** @var array<string, int> */
    private array $vocabulary = [];

    /** @var array<string, float> */
    private array $idf = [];

    /** @var array<int, array<int, float>> */
    private array $classTermWeights = [0 => [], 1 => []];

    /** @var array<int, float> */
    private array $classTotals = [0 => 0.0, 1 => 0.0];

    /** @var array<int, int> */
    private array $classDocCounts = [0 => 0, 1 => 0];

    /** @var array<int, float> */
    private array $featureLogProb = [0 => [], 1 => []];

    /** @var array<int, float> */
    private array $classLogPrior = [0 => 0.0, 1 => 0.0];

    private int $docCount = 0;

    private int $vocabularySize = 0;

    private float $alpha = 1.0;

    /**
     * @param  list<list<string>>  $documents
     * @param  list<int>  $labels
     */
    public function train(array $documents, array $labels, float $alpha = 1.0): void
    {
        $this->reset();
        $this->alpha = $alpha;
        $this->docCount = count($documents);
        $documentFrequencies = [];
        $tokenCounts = [];

        foreach ($documents as $index => $tokens) {
            $counts = array_count_values($tokens);
            $tokenCounts[$index] = $counts;
            $label = (int) ($labels[$index] ?? 0);
            $this->classDocCounts[$label] = ($this->classDocCounts[$label] ?? 0) + 1;

            foreach ($counts as $token => $count) {
                $documentFrequencies[$token] = ($documentFrequencies[$token] ?? 0) + 1;
            }
        }

        ksort($documentFrequencies);

        foreach ($documentFrequencies as $token => $frequency) {
            $this->vocabulary[$token] = $this->vocabularySize++;
            $this->idf[$token] = log((1 + $this->docCount) / (1 + $frequency)) + 1;
        }

        foreach ($documents as $index => $tokens) {
            $counts = $tokenCounts[$index] ?? [];
            $total = array_sum($counts);

            if ($total === 0) {
                continue;
            }

            $label = (int) ($labels[$index] ?? 0);
            $vector = [];
            $norm = 0.0;

            foreach ($counts as $token => $count) {
                $termIndex = $this->vocabulary[$token] ?? null;

                if ($termIndex === null) {
                    continue;
                }

                $weight = ($count / $total) * ($this->idf[$token] ?? 0.0);
                $vector[$termIndex] = $weight;
                $norm += $weight * $weight;
            }

            $norm = sqrt($norm);

            if ($norm === 0.0) {
                continue;
            }

            foreach ($vector as $termIndex => $weight) {
                $normalized = $weight / $norm;
                $this->classTermWeights[$label][$termIndex] = ($this->classTermWeights[$label][$termIndex] ?? 0.0) + $normalized;
                $this->classTotals[$label] += $normalized;
            }
        }

        $this->buildFeatureLogProbabilities();
    }

    /**
     * @param  list<string>  $tokens
     * @return array{label: int, proba_pos: float}
     */
    public function predict(array $tokens): array
    {
        $vector = $this->vectorize($tokens);
        $scores = [
            0 => $this->classLogPrior[0],
            1 => $this->classLogPrior[1],
        ];

        foreach ($vector as $termIndex => $weight) {
            $scores[0] += $weight * ($this->featureLogProb[0][$termIndex] ?? 0.0);
            $scores[1] += $weight * ($this->featureLogProb[1][$termIndex] ?? 0.0);
        }

        $max = max($scores);
        $exp0 = exp($scores[0] - $max);
        $exp1 = exp($scores[1] - $max);
        $sum = $exp0 + $exp1;
        $probaPos = $sum > 0 ? $exp1 / $sum : 0.5;

        return [
            'label' => $probaPos >= 0.5 ? 1 : 0,
            'proba_pos' => round($probaPos, 4),
        ];
    }

    /**
     * @param  list<list<string>>  $documents
     * @return list<array{label: int, proba_pos: float}>
     */
    public function predictAll(array $documents): array
    {
        return array_map(fn (array $tokens): array => $this->predict($tokens), $documents);
    }

    public function vocabularySize(): int
    {
        return $this->vocabularySize;
    }

    /**
     * @param  list<string>  $tokens
     * @return array<int, float>
     */
    private function vectorize(array $tokens): array
    {
        if ($tokens === [] || $this->vocabularySize === 0) {
            return [];
        }

        $counts = array_count_values($tokens);
        $total = array_sum($counts);

        if ($total === 0) {
            return [];
        }

        $vector = [];
        $norm = 0.0;

        foreach ($counts as $token => $count) {
            $termIndex = $this->vocabulary[$token] ?? null;

            if ($termIndex === null) {
                continue;
            }

            $weight = ($count / $total) * ($this->idf[$token] ?? 0.0);
            $vector[$termIndex] = $weight;
            $norm += $weight * $weight;
        }

        $norm = sqrt($norm);

        if ($norm === 0.0) {
            return [];
        }

        foreach ($vector as $termIndex => $weight) {
            $vector[$termIndex] = $weight / $norm;
        }

        return $vector;
    }

    private function buildFeatureLogProbabilities(): void
    {
        $this->featureLogProb = [0 => [], 1 => []];
        $this->classLogPrior = [0 => 0.0, 1 => 0.0];

        foreach ([0, 1] as $label) {
            $docCount = max($this->classDocCounts[$label], 1);
            $this->classLogPrior[$label] = log($docCount / max($this->docCount, 1));
            $denominator = $this->classTotals[$label] + $this->alpha * $this->vocabularySize;

            foreach ($this->vocabulary as $termIndex) {
                $numerator = ($this->classTermWeights[$label][$termIndex] ?? 0.0) + $this->alpha;
                $this->featureLogProb[$label][$termIndex] = $denominator > 0
                    ? log($numerator / $denominator)
                    : 0.0;
            }
        }
    }

    private function reset(): void
    {
        $this->vocabulary = [];
        $this->idf = [];
        $this->classTermWeights = [0 => [], 1 => []];
        $this->classTotals = [0 => 0.0, 1 => 0.0];
        $this->classDocCounts = [0 => 0, 1 => 0];
        $this->featureLogProb = [0 => [], 1 => []];
        $this->classLogPrior = [0 => 0.0, 1 => 0.0];
        $this->docCount = 0;
        $this->vocabularySize = 0;
    }
}
