<?php

namespace App\Http\Controllers;

use App\Support\ArtifactRepository;
use Inertia\Inertia;
use Inertia\Response;

class SentimentAnalysisController extends Controller
{
    public function __invoke(ArtifactRepository $artifacts): Response
    {
        return Inertia::render('sentiment/analisis', [
            'summary' => $artifacts->summary(),
            'words' => $artifacts->words(),
        ]);
    }
}
