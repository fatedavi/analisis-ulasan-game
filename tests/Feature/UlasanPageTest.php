<?php

use App\Support\DemoArtifacts;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\PreparesDemoArtifacts;

uses(PreparesDemoArtifacts::class);

afterEach(function () {
    $this->cleanupArtifacts();
});

/** @return array<string, mixed> */
function ulasanProps(TestResponse $response): array
{
    return $response->assertOk()->inertiaProps();
}

test('ulasan page renders reviews with stars, comment, and analysis', function () {
    $this->prepareArtifacts();

    $props = ulasanProps($this->get(route('sentiment.reviews')));

    expect($props['summary']['total'])->toBe(DemoArtifacts::TOTAL)
        ->and($props['reviews']['total'])->toBe(DemoArtifacts::TOTAL)
        ->and($props['reviews']['positif'])->toBe(2520)
        ->and($props['reviews']['negatif'])->toBe(980)
        ->and($props['reviews']['last_page'])->toBe(292)
        ->and($props['reviews']['per_page'])->toBe(12)
        ->and($props['reviews']['page'])->toBe(1)
        ->and(count($props['reviews']['data']))->toBe(12);

    $first = $props['reviews']['data'][0];

    expect($first['rating'])->toBeBetween(1, 5)
        ->and($first['review'])->not->toBeEmpty()
        ->and($first['username'])->not->toBeEmpty()
        ->and($first['date'])->not->toBeEmpty()
        ->and($first['pred_label'])->toBeIn(['positif', 'negatif'])
        ->and($first['true_label'])->toBeIn(['positif', 'negatif'])
        ->and($first['proba_pos'])->toBeFloat();

    $this->get(route('sentiment.reviews'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sentiment/ulasan'));
});

test('ulasan page filters by sentiment label and rating', function () {
    $this->prepareArtifacts();

    $positive = ulasanProps($this->get(route('sentiment.reviews', ['label' => 'positif'])));

    expect($positive['reviews']['label'])->toBe('positif')
        ->and($positive['reviews']['negatif'])->toBe(0)
        ->and($positive['reviews']['total'])->toBe($positive['reviews']['positif']);

    $stars = ulasanProps($this->get(route('sentiment.reviews', ['rating' => 5])));

    expect($stars['reviews']['rating'])->toBe(5)
        ->and($stars['reviews']['total'])->toBe($stars['reviews']['rating_distribution'][5])
        ->and(array_unique(array_column($stars['reviews']['data'], 'rating')))->toBe([5]);
});

test('ulasan page searches review text', function () {
    $this->prepareArtifacts();

    $props = ulasanProps($this->get(route('sentiment.reviews', ['q' => 'lag'])));

    expect($props['reviews']['q'])->toBe('lag')
        ->and($props['reviews']['total'])->toBeGreaterThan(0);

    foreach ($props['reviews']['data'] as $row) {
        expect(mb_stripos((string) $row['review'], 'lag'))->not->toBeFalse();
    }
});

test('ulasan page paginates without repeating rows', function () {
    $this->prepareArtifacts();

    $first = ulasanProps($this->get(route('sentiment.reviews')));
    $second = ulasanProps($this->get(route('sentiment.reviews', ['page' => 2])));

    $firstIds = array_column($first['reviews']['data'], 'review_id');
    $secondIds = array_column($second['reviews']['data'], 'review_id');

    expect($firstIds)->toHaveCount(12)
        ->and($second['reviews']['page'])->toBe(2)
        ->and($secondIds)->toHaveCount(12)
        ->and(array_intersect($firstIds, $secondIds))->toBe([]);
});

test('ulasan page still renders when artifacts are missing', function () {
    $this->prepareArtifacts(empty: true);

    $this->get(route('sentiment.reviews'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sentiment/ulasan')
            ->where('reviews', null));
});
