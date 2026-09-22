<?php

namespace App\Http\Controllers;

use App\Support\ArtifactRepository;
use Inertia\Inertia;
use Inertia\Response;

class VisualizationController extends Controller
{
    public function __invoke(ArtifactRepository $artifacts): Response
    {
        return Inertia::render('sentiment/visualisasi', [
            'evaluation' => $artifacts->evaluation(),
        ]);
    }
}
