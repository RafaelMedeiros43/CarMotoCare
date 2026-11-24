<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDeadlineRequest;
use App\Http\Requests\ListPendingDeadlinesRequest;
use App\Http\Requests\ShowVehicleRequest;
use App\Http\Resources\DeadlineResource;
use App\Services\DeadlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeadlineController extends Controller
{
    public function __construct(private readonly DeadlineService $deadlines) {}

    /** GET /vehicles/{vehicleId}/deadlines */
    public function index(ShowVehicleRequest $request, string $vehicleId): AnonymousResourceCollection
    {
        return DeadlineResource::collection($this->deadlines->listForVehicle($vehicleId, $request->userId()));
    }

    /** POST /vehicles/{vehicleId}/deadlines */
    public function store(CreateDeadlineRequest $request, string $vehicleId): JsonResponse
    {
        $deadline = $this->deadlines->create($vehicleId, $request->userId(), $request->validated());

        return (new DeadlineResource($deadline))->response()->setStatusCode(201);
    }

    /** GET /deadlines */
    public function pending(ListPendingDeadlinesRequest $request): AnonymousResourceCollection
    {
        return DeadlineResource::collection(
            $this->deadlines->listPending($request->validated('type'), $request->validated('dueBefore'))
        );
    }
}
