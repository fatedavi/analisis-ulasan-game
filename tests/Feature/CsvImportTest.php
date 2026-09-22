<?php

namespace Tests\Feature;

use App\Support\ArtifactRepository;
use Tests\Feature\Concerns\PreparesDemoArtifacts;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;

uses(PreparesDemoArtifacts::class);

function tempImportCsv(string $dir): string
{
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir.DIRECTORY_SEPARATOR.'reviews_test.csv';

    file_put_contents($path, <<<'CSV'
username,rating,review,date
Pengguna 1,5,Game bagus dan seru,2026-01-01
Pengguna 2,4,Keren sekali grafisnya wow,2026-01-02
Pengguna 3,3,Biasa saja,2026-01-03
Pengguna 4,2,Banyak bug dan sering lag,2026-01-04
Pengguna 5,1,Sampah game ini,2026-01-05
Pengguna 6,3,Lumayan,2026-01-06
Pengguna 7,4,Mantap recommended banget,2026-01-07
Pengguna 8,5,Bagus sekali,2026-01-08
Pengguna 9,1,Parah menyesal install,2026-01-09
Pengguna 10,2,Force close terus,2026-01-10
Pengguna 11,5,😀😃😂,2026-01-11
Pengguna 12,5,ok,2026-01-12
CSV);

    return $path;
}

it('mengimpor CSV menjadi artefak nyata sesuai aturan rating proxy', function () {
    $this->prepareArtifacts(empty: true);
    $csv = tempImportCsv($this->artifactsDir);

    artisan('artifacts:import', ['--file' => $csv, '--top' => '50'])->assertSuccessful();

    $repository = new ArtifactRepository;

    foreach (['run_meta.json', 'data_summary.json', 'results.json', 'oof_predictions.csv', 'words.json', 'reviews.json'] as $file) {
        expect($repository->has($file))->toBeTrue();
    }

    $summary = $repository->summary();

    expect($summary['demo'])->toBeFalse()
        ->and($summary['source'])->toBe('oof_predictions')
        ->and($summary['total'])->toBe(8)
        ->and($summary['positive'] + $summary['negative'])->toBe(8)
        ->and($repository->meta()['source'])->toBe('csv-import');

    $reviews = $repository->reviews();
    $reviewTexts = array_column($reviews['data'], 'review');

    expect($reviews['total'])->toBe(8)
        ->and($reviews['last_page'])->toBe(1)
        ->and($reviews['positif'] + $reviews['negatif'])->toBe(8)
        ->and(in_array('Game bagus dan seru', $reviewTexts, true))->toBeTrue();

    $positiveOnly = $repository->reviews(label: 'positif');

    expect($positiveOnly['negatif'])->toBe(0)
        ->and($positiveOnly['total'])->toBe($positiveOnly['positif']);

    $evaluation = $repository->evaluation();

    $confusion = $evaluation['confusion_matrix'];

    expect(array_sum([$confusion['tn'], $confusion['fp'], $confusion['fn'], $confusion['tp']]))->toBe(8)
        ->and($confusion['tp'] + $confusion['fn'])->toBe(4)
        ->and($confusion['tn'] + $confusion['fp'])->toBe(4)
        ->and(count($evaluation['grid']))->toBe(12);

    getJson('/api/summary')
        ->assertOk()
        ->assertJson([
            'demo' => false,
            'total' => 8,
        ]);

    getJson('/api/healthz')
        ->assertOk()
        ->assertJson([
            'artifacts' => ['words.json' => true],
            'demo' => false,
        ]);

    unlink($csv);
    $this->cleanupArtifacts();
});

it('menulis words.json dengan frekuensi kata yang konsisten', function () {
    $this->prepareArtifacts(empty: true);
    $csv = tempImportCsv($this->artifactsDir);

    artisan('artifacts:import', ['--file' => $csv, '--top' => '50'])->assertSuccessful();

    $words = (new ArtifactRepository)->words();

    expect($words['total_docs'])->toBe(8)
        ->and($words['words'])->not->toBeEmpty()
        ->and(array_sum(array_column($words['words'], 'count')))->toBeLessThanOrEqual($words['total_tokens']);

    $tokens = array_column($words['words'], 'token');

    expect(in_array('game', $tokens, true))->toBeTrue()
        ->and(in_array('saja', $tokens, true))->toBeFalse();

    unlink($csv);
    $this->cleanupArtifacts();
});
