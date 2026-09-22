<?php

namespace Tests\Unit;

use App\Support\CsvReviewImporter;
use App\Support\LexiconClassifier;
use Tests\TestCase;

uses(TestCase::class);

function importerTempCsv(string $dir, string $name): string
{
    $path = $dir.DIRECTORY_SEPARATOR.$name;

    file_put_contents($path, <<<'CSV'
username,rating,review,date
A,5,Game bagus dan seru,2026-01-01
B,3,Biasa saja,2026-01-02
C,1,Banyak bug dan lag,2026-01-03
D,5,😀😃😂,2026-01-04
CSV);

    return $path;
}

it('menghapus stopword dan token pendek dari teks', function () {
    $importer = new CsvReviewImporter(new LexiconClassifier);

    expect($importer->tokenize('Game bagus dan seru'))->toBe(['game', 'bagus', 'seru'])
        ->and($importer->tokenize('Keren sekali grafisnya wow'))->toBe(['keren', 'grafisnya', 'wow'])
        ->and($importer->tokenize('😀😃😂'))->toBe([])
        ->and($importer->tokenize('ok ok'))->toBe([]);
});

it('menghitung ringkasan impor sesuai aturan proxy rating', function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ak-importer-'.uniqid('', true);
    mkdir($dir);
    $csv = importerTempCsv($dir, 'in.csv');

    config()->set('artifacts.path', $dir.DIRECTORY_SEPARATOR.'out');

    $summary = (new CsvReviewImporter(new LexiconClassifier))->import($csv);

    expect($summary['raw'])->toBe(4)
        ->and($summary['included'])->toBe(2)
        ->and($summary['class_distribution']['positif'])->toBe(1)
        ->and($summary['class_distribution']['negatif'])->toBe(1)
        ->and($summary['excluded']['unclear'])->toBe(1)
        ->and($summary['excluded']['empty_after_preprocessing'])->toBe(1);

    unlink($csv);
    array_map('unlink', glob($dir.'/out/*') ?: []);
    rmdir($dir.'/out');
    rmdir($dir);
});

it('impor bersifat deterministik untuk berkas yang sama', function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ak-importer-'.uniqid('', true);
    mkdir($dir);
    $csv = importerTempCsv($dir, 'in.csv');

    config()->set('artifacts.path', $dir.'/out');
    (new CsvReviewImporter(new LexiconClassifier))->import($csv, 50);
    $first = file_get_contents($dir.'/out/run_meta.json');

    config()->set('artifacts.path', $dir.'/out2');
    (new CsvReviewImporter(new LexiconClassifier))->import($csv, 50);
    $second = file_get_contents($dir.'/out2/run_meta.json');

    expect($first)->toBe($second);

    unlink($csv);

    foreach ([$dir.'/out', $dir.'/out2'] as $out) {
        array_map('unlink', glob($out.'/*') ?: []);
        rmdir($out);
    }

    rmdir($dir);
});

it('menulis reviews.json dengan komentar, rating, dan hasil analisis', function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ak-importer-'.uniqid('', true);
    mkdir($dir);
    $csv = importerTempCsv($dir, 'in.csv');

    config()->set('artifacts.path', $dir.'/out');
    (new CsvReviewImporter(new LexiconClassifier))->import($csv);

    $reviews = json_decode((string) file_get_contents($dir.'/out/reviews.json'), true);

    expect($reviews['run_id'])->toHaveLength(6)
        ->and($reviews['total_docs'])->toBe(2)
        ->and(array_column($reviews['reviews'], 'review_id'))->toBe(['r000002', 'r000004']);

    $byText = [];

    foreach ($reviews['reviews'] as $row) {
        $byText[$row['review']] = $row;
    }

    expect($byText['Game bagus dan seru']['username'])->toBe('A')
        ->and($byText['Game bagus dan seru']['rating'])->toBe(5)
        ->and($byText['Game bagus dan seru']['date'])->toBe('2026-01-01')
        ->and($byText['Game bagus dan seru']['true_label'])->toBe('positif')
        ->and($byText['Banyak bug dan lag']['true_label'])->toBe('negatif')
        ->and($byText['Banyak bug dan lag']['pred_label'])->toBeIn(['positif', 'negatif'])
        ->and($byText['Banyak bug dan lag']['proba_pos'])->toBeFloat()
        ->and(array_column($byText, 'true_label'))->not->toContain('netral');

    unlink($csv);
    array_map('unlink', glob($dir.'/out/*') ?: []);
    rmdir($dir.'/out');
    rmdir($dir);
});
