<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PredictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictController extends Controller
{
    public function __invoke(Request $request, PredictService $predict): JsonResponse
    {
        $text = (string) $request->string('text')->toString();

        if (mb_strlen($text) < 1 || mb_strlen($text) > 1000) {
            return response()->json(['error' => 'tidak_dapat_diklasifikasikan'], 422);
        }

        $result = $predict->classify($text);

        if ($result === null) {
            return response()->json(['error' => 'tidak_dapat_diklasifikasikan'], 422);
        }

        return response()->json($result);
    }
}
