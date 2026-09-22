<?php

use Tests\Feature\Concerns\PreparesDemoArtifacts;

uses(PreparesDemoArtifacts::class);

beforeEach(function () {
    $this->prepareArtifacts();
});

afterEach(function () {
    $this->cleanupArtifacts();
});

test('predict classifies a positive review', function () {
    $this->postJson(route('api.predict'), ['text' => 'Game ini sangat mantap dan seru, recommended!'])
        ->assertOk()
        ->assertJsonPath('label', 'positif')
        ->assertJsonPath('demo', true)
        ->assertJsonStructure(['proba' => ['negatif', 'positif']]);

    $proba = $this->postJson(route('api.predict'), ['text' => 'Game ini sangat mantap dan seru, recommended!'])->json('proba');
    expect((float) $proba['negatif'] + (float) $proba['positif'])->toBe(1.0);
});

test('predict classifies a negative review', function () {
    $this->postJson(route('api.predict'), ['text' => 'Game jelek, banyak bug dan lag parah'])
        ->assertOk()
        ->assertJsonPath('label', 'negatif');
});

test('predict handles negation like "tidak bagus"', function () {
    $this->postJson(route('api.predict'), ['text' => 'Grafik tidak bagus'])
        ->assertOk()
        ->assertJsonPath('label', 'negatif');
});

test('predict returns 422 for empty, oversized, and token-less input', function () {
    $this->postJson(route('api.predict'), ['text' => ''])
        ->assertStatus(422)
        ->assertJsonPath('error', 'tidak_dapat_diklasifikasikan');

    $this->postJson(route('api.predict'), ['text' => str_repeat('a', 1001)])
        ->assertStatus(422);

    $this->postJson(route('api.predict'), ['text' => '!!!...###'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'tidak_dapat_diklasifikasikan');
});
