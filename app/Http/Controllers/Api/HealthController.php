<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ArtifactRepository;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(ArtifactRepository $artifacts): JsonResponse
    {
        $status = $artifacts->status();

        return response()->json([
            'status' => $status['present'] === [] || in_array(false, $status['present'], true) ? 'degraded' : 'ok',
            'artifacts' => $status['present'],
            'demo' => $status['demo'],
            'run_id' => $status['run_id'],
            'errors' => $status['errors'],
        ]);
    }
}
