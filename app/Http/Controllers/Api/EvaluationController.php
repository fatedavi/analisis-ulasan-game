<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ArtifactRepository;
use Illuminate\Http\JsonResponse;

class EvaluationController extends Controller
{
    public function __invoke(ArtifactRepository $artifacts): JsonResponse
    {
        $evaluation = $artifacts->evaluation();

        if ($evaluation === null) {
            return response()->json([
                'error' => 'artifacts_unavailable',
                'message' => 'Artefak hasil analisis belum tersedia. Jalankan `python -m src.train_eval` atau `php artisan artifacts:demo`.',
                'details' => $artifacts->errors(),
            ], 503);
        }

        return response()->json($evaluation);
    }
}
