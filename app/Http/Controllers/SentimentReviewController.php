<?php

namespace App\Http\Controllers;

use App\Support\ArtifactRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SentimentReviewController extends Controller
{
    public function __invoke(Request $request, ArtifactRepository $artifacts): Response
    {
        $label = $request->string('label')->toString();
        $label = in_array($label, ['positif', 'negatif'], true) ? $label : null;

        $rating = (int) $request->integer('rating');

        if ($rating < 1 || $rating > 5) {
            $rating = 0;
        }

        $q = $request->string('q')->trim()->toString();

        if ($q === '' || mb_strlen($q) > 80) {
            $q = null;
        }

        $page = max(1, $request->integer('page', 1));

        return Inertia::render('sentiment/ulasan', [
            'summary' => $artifacts->summary(),
            'reviews' => $artifacts->reviews(
                label: $label,
                rating: $rating > 0 ? $rating : null,
                q: $q,
                page: $page,
            ),
        ]);
    }
}
