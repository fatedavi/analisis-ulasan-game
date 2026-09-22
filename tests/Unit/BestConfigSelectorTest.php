<?php

use App\Support\BestConfigSelector;

function configRow(float $alpha, int $k, float $f1, float $std = 0.01, float $accuracy = 0.80): array
{
    return [
        'alpha' => $alpha,
        'k' => $k,
        'metrics' => [
            'macro' => ['f1' => ['mean' => $f1, 'std' => $std]],
            'accuracy' => ['mean' => $accuracy],
        ],
    ];
}

test('best config selector returns the config with the highest macro-F1', function () {
    $configs = [
        configRow(0.5, 5, 0.70),
        configRow(1.0, 10, 0.75),
    ];

    $best = (new BestConfigSelector)->select($configs);

    expect($best)->toEqual($configs[1]);
});

test('best config selector returns null for an empty list', function () {
    expect((new BestConfigSelector)->select([]))->toBeNull();
});

test('best config selector uses accuracy as first tie-breaker', function () {
    $configs = [
        configRow(1.0, 5, 0.75, accuracy: 0.80),
        configRow(1.0, 10, 0.75, accuracy: 0.84),
    ];

    $best = (new BestConfigSelector)->select($configs);

    expect($best['k'])->toBe(10);
});

test('best config selector uses smaller std as second tie-breaker', function () {
    $configs = [
        configRow(1.0, 5, 0.75, std: 0.020),
        configRow(1.0, 5, 0.75, std: 0.005),
    ];

    $best = (new BestConfigSelector)->select($configs);

    expect(data_get($best, 'metrics.macro.f1.std'))->toBe(0.005);
});

test('best config selector prefers smaller alpha then larger K', function () {
    $configs = [
        configRow(1.5, 10, 0.75),
        configRow(1.0, 5, 0.75),
    ];

    $best = (new BestConfigSelector)->select($configs);

    expect($best['alpha'])->toBe(1.0)
        ->and($best['k'])->toBe(5);
});
