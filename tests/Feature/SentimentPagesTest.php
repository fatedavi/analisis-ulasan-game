<?php

use Tests\Feature\Concerns\PreparesDemoArtifacts;

uses(PreparesDemoArtifacts::class);

afterEach(function () {
    $this->cleanupArtifacts();
});

test('sentiment analysis page renders with demo summary', function () {
    $this->prepareArtifacts();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sentiment/analisis')->has('summary'));
});

test('visualization page renders with demo evaluation', function () {
    $this->prepareArtifacts();

    $this->get(route('sentiment.visualization'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sentiment/visualisasi')->has('evaluation'));
});

test('sentiment page still renders when artifacts are missing', function () {
    $this->prepareArtifacts(empty: true);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sentiment/analisis')->where('summary', null));
});
