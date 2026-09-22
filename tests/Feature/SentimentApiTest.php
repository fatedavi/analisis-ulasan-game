<?php

use Tests\Feature\Concerns\PreparesDemoArtifacts;

uses(PreparesDemoArtifacts::class);

beforeEach(function () {
    $this->prepareArtifacts();
});

afterEach(function () {
    $this->cleanupArtifacts();
});

test('api summary returns dashboard numbers from oof predictions', function () {
    $this->getJson(route('api.summary'))
        ->assertOk()
        ->assertJson([
            'total' => 3500,
            'positive' => 2520,
            'negative' => 980,
            'sentiment_score' => 72,
            'source' => 'oof_predictions',
            'demo' => true,
        ])
        ->assertJsonStructure(['run_id', 'generated_at']);
});

test('api evaluation returns confusion matrix, metrics, and the 12-combination grid', function () {
    $response = $this->getJson(route('api.evaluation'))
        ->assertOk()
        ->assertJsonPath('confusion_matrix.orientation', 'rows=actual, cols=predicted')
        ->assertJsonPath('confusion_matrix.tp', 2340)
        ->assertJsonPath('confusion_matrix.tn', 720)
        ->assertJsonPath('confusion_matrix.fp', 180)
        ->assertJsonPath('confusion_matrix.fn', 260)
        ->assertJsonPath('baseline_majority_accuracy', round(2520 / 3500, 6));

    $best = $response->json('best');
    expect((float) $best['alpha'])->toBe(1.0)
        ->and($best['k'])->toBe(10);

    $grid = $response->json('grid');
    expect(count($grid))->toBe(12);

    $matrix = $response->json('confusion_matrix');
    expect($matrix['tn'] + $matrix['fp'] + $matrix['fn'] + $matrix['tp'])->toBe(3500);

    $metrics = $response->json('metrics_mean_over_folds');
    expect($metrics)
        ->toHaveKeys(['accuracy', 'positive', 'negative', 'macro'])
        ->and($metrics['accuracy'])->toHaveKeys(['mean', 'std']);
});

test('api healthz reports ok with demo artifacts present', function () {
    $this->getJson(route('api.health'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('demo', true);
});

test('api summary returns 503 when artifacts are missing', function () {
    $this->cleanupArtifacts();
    $this->prepareArtifacts(empty: true);

    $this->getJson(route('api.summary'))
        ->assertStatus(503)
        ->assertJsonPath('error', 'artifacts_unavailable');
});

test('api evaluation returns 503 when results.json is corrupt', function () {
    $this->writeArtifact('results.json', '{not valid json');

    $this->getJson(route('api.evaluation'))
        ->assertStatus(503)
        ->assertJson(['details' => ['results.json' => 'invalid']]);
});
