<?php

use App\Support\KFoldEvaluator;

function kfoldRows(int $perClass = 10): array
{
    $rows = [];

    for ($label = 0; $label <= 1; $label++) {
        for ($i = 0; $i < $perClass; $i++) {
            $token = $label === 1 ? 'bagus' : 'buruk';
            $rows[] = [
                'review_id' => 'r'.str_pad((string) count($rows), 6, '0', STR_PAD_LEFT),
                'tokens' => [$token, 'game'],
                'label_true' => $label,
            ];
        }
    }

    return $rows;
}

it('membagi K=10 menjadi lima iterasi 8 latih dan 2 uji tanpa tumpang tindih', function () {
    $result = (new KFoldEvaluator)->evaluate(kfoldRows());
    $plan = $result['fold_plans']['10'];

    expect($plan['fold_count'])->toBe(10)
        ->and($plan['test_folds_per_iteration'])->toBe(2)
        ->and($plan['iterations'])->toHaveCount(5);

    $tested = [];

    foreach ($plan['iterations'] as $iteration) {
        expect($iteration['test_folds'])->toHaveCount(2)
            ->and($iteration['train_size'])->toBe(16)
            ->and($iteration['test_size'])->toBe(4);

        $tested = array_merge($tested, $iteration['test_folds']);
    }

    sort($tested);

    expect($tested)->toBe(range(1, 10));
});

it('menghasilkan 12 konfigurasi dengan metrik per fold dan OOF unik', function () {
    $result = (new KFoldEvaluator)->evaluate(kfoldRows());

    expect($result['configs'])->toHaveCount(12)
        ->and($result['best'])->not->toBeNull();

    $k10 = array_values(array_filter(
        $result['configs'],
        fn (array $config): bool => $config['k'] === 10 && $config['alpha'] === 1.0,
    ))[0];

    expect($k10['per_fold'])->toHaveCount(10)
        ->and(array_column($k10['per_fold'], 'fold'))->toBe(range(1, 10))
        ->and($k10['oof'])->toHaveCount(20);

    foreach ($k10['per_fold'] as $fold) {
        expect($fold['accuracy'])->toBeBetween(0.0, 1.0)
            ->and($fold['macro']['f1']['mean'])->toBeBetween(0.0, 1.0);
    }
});
