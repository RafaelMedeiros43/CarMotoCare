<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDeadlineRequest;
use App\Http\Resources\DeadlineResource;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeadlineController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    /** GET /vehicles/{vehicleId}/deadlines */
    public function index(Request $request, string $vehicleId): AnonymousResourceCollection
    {
        return DeadlineResource::collection(
            $this->vehicles->listDeadlines($request->attributes->get('userId'), $vehicleId)
        );
    }

    /** POST /vehicles/{vehicleId}/deadlines */
    public function store(CreateDeadlineRequest $request, string $vehicleId): JsonResponse
    {
        $deadline = $this->vehicles->createDeadline(
            $request->attributes->get('userId'),
            $vehicleId,
            $request->validated(),
        );

        return (new DeadlineResource($deadline))->response()->setStatusCode(201);
    }
}
