<?php

use App\Support\BestConfigSelector;
use App\Support\DemoArtifacts;
use App\Support\LexiconClassifier;

function demoArtifacts(): DemoArtifacts
{
    return new DemoArtifacts(new LexiconClassifier);
}

function parseOofCsv(string $content): array
{
    $lines = array_values(array_filter(explode("\n", trim($content))));

    return array_map(
        fn (string $line): array => str_getcsv($line),
        array_slice($lines, 1),
    );
}

test('demo artifacts satisfy the SC-4 invariants', function () {
    $files = demoArtifacts()->files();

    $oof = parseOofCsv($files['oof_predictions.csv']);

    expect(count($oof))->toBe(DemoArtifacts::TOTAL);

    $positivePredictions = 0;
    $negativePredictions = 0;
    $counts = ['tn' => 0, 'fp' => 0, 'fn' => 0, 'tp' => 0];

    foreach ($oof as $row) {
        [$reviewId, $true, $pred, $proba, $fold] = $row;
        $true = (int) $true;
        $pred = (int) $pred;
        $fold = (int) $fold;

        expect($true)->toBeIn([0, 1]);
        expect($pred)->toBeIn([0, 1]);
        expect($fold)->toBeBetween(1, 10);

        if ($pred == 1) {
            $positivePredictions++;
        } else {
            $negativePredictions++;
        }

        if ($true == 0 && $pred == 0) {
            $counts['tn']++;
        } elseif ($true == 0 && $pred == 1) {
            $counts['fp']++;
        } elseif ($true == 1 && $pred == 0) {
            $counts['fn']++;
        } else {
            $counts['tp']++;
        }
    }

    expect($positivePredictions + $negativePredictions)->toBe(DemoArtifacts::TOTAL);
    expect(array_sum($counts))->toBe(DemoArtifacts::TOTAL);
});

test('demo artifacts contain exactly 12 grid configurations and a valid best', function () {
    $files = demoArtifacts()->files();
    $results = json_decode($files['results.json'], true, flags: JSON_THROW_ON_ERROR);

    expect(count($results['configs']))->toBe(12);
    expect((float) $results['best']['alpha'])->toBe(1.0)
        ->and((int) $results['best']['k'])->toBe(10);

    $best = (new BestConfigSelector)->select($results['configs']);

    expect((float) $best['alpha'])->toBe(1.0)
        ->and((int) $best['k'])->toBe(10)
        ->and($results['baseline_majority_accuracy'])->toBe(round(2600 / 3500, 6));
});

test('demo run meta is clearly marked as demo', function () {
    $meta = json_decode(demoArtifacts()->files()['run_meta.json'], true, flags: JSON_THROW_ON_ERROR);

    expect($meta['demo'])->toBeTrue()
        ->and($meta['source'])->toBe('demo')
        ->and($meta['run_id'])->toHaveLength(6)
        ->and($meta['seed'])->toBe(42);
});

test('data summary counts are consistent', function () {
    $summary = json_decode(demoArtifacts()->files()['data_summary.json'], true, flags: JSON_THROW_ON_ERROR);

    $raw = $summary['raw'];
    $excluded = array_sum($summary['excluded']);

    expect($raw - $excluded)->toBe($summary['included'])
        ->and($summary['class_distribution']['positif'])->toBe(DemoArtifacts::POSITIVE_TRUE)
        ->and($summary['class_distribution']['negatif'])->toBe(DemoArtifacts::NEGATIVE_TRUE);
});

test('demo artifacts include a deterministic word cloud payload', function () {
    $files = demoArtifacts()->files();

    expect(isset($files['words.json']))->toBeTrue();

    $words = json_decode($files['words.json'], true, flags: JSON_THROW_ON_ERROR);

    expect($words['run_id'])->toHaveLength(6)
        ->and($words['total_docs'])->toBe(DemoArtifacts::TOTAL)
        ->and($words['words'])->not->toBeEmpty()
        ->and(array_sum(array_column($words['words'], 'count')))->toBe($words['total_tokens'])
        ->and(round(array_sum(array_column($words['words'], 'share')), 3))->toBe(1.0);

    $labels = array_values(array_unique(array_column($words['words'], 'label')));

    expect($labels)->toBe(['positif', 'negatif']);
});

test('demo artifacts include a review listing consistent with oof predictions', function () {
    $demo = new DemoArtifacts(new LexiconClassifier);
    $files = $demo->files();

    expect(isset($files['reviews.json']))->toBeTrue();

    $reviews = json_decode($files['reviews.json'], true, flags: JSON_THROW_ON_ERROR);

    expect($reviews['run_id'])->toHaveLength(6)
        ->and($reviews['total_docs'])->toBe(DemoArtifacts::TOTAL)
        ->and(count($reviews['reviews']))->toBe(DemoArtifacts::TOTAL);

    $positif = 0;
    $negatif = 0;
    $seenIds = [];

    foreach ($reviews['reviews'] as $row) {
        $row['pred_label'] === 'positif' ? $positif++ : $negatif++;

        expect($row['rating'])->toBeBetween(1, 5)
            ->and($row['proba_pos'])->toBeIn([0.87, 0.21])
            ->and($row['review_id'])->toMatch('/^demo_\d{6}$/')
            ->and($seenIds)->not->toContain($row['review_id']);

        if ($row['true_label'] === 'positif') {
            expect($row['rating'])->toBeGreaterThanOrEqual(4);
        } else {
            expect($row['rating'])->toBeLessThanOrEqual(2);
        }

        $seenIds[] = $row['review_id'];
    }

    $oof = parseOofCsv($files['oof_predictions.csv']);
    $oofPositif = count(array_filter($oof, fn (array $row): bool => (int) $row[2] === 1));

    expect($positif)->toBe($oofPositif)
        ->and($positif + $negatif)->toBe(DemoArtifacts::TOTAL);

    expect($files['reviews.json'])->toBe(demoArtifacts()->files()['reviews.json']);
});
