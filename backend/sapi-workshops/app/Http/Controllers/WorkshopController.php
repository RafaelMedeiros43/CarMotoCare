<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchWorkshopsRequest;
use App\Services\WorkshopService;
use Illuminate\Http\JsonResponse;

final class WorkshopController extends Controller
{
    public function index(SearchWorkshopsRequest $request, WorkshopService $service): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $workshops = $service->search(
            (float) $validated['lat'],
            (float) $validated['lon'],
            isset($validated['radiusKm']) ? (float) $validated['radiusKm'] : 10.0,
            isset($validated['type']) ? (string) $validated['type'] : null,
            isset($validated['q']) ? (string) $validated['q'] : null,
        );

        return response()->json($workshops);
    }
}
