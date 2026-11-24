<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVehicleRequest;
use App\Http\Requests\ListVehiclesRequest;
use App\Http\Requests\ShowVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    /** GET /vehicles */
    public function index(ListVehiclesRequest $request): JsonResponse
    {
        [$vehicles, $total] = $this->vehicles->list(
            $request->userId(),
            $request->validated('type'),
            (int) $request->validated('limit', ListVehiclesRequest::DEFAULT_LIMIT),
            (int) $request->validated('offset', 0),
        );

        return VehicleResource::collection($vehicles)
            ->response()
            ->header('X-Total-Count', $total);
    }

    /** POST /vehicles */
    public function store(CreateVehicleRequest $request): JsonResponse
    {
        $vehicle = $this->vehicles->create($request->userId(), $request->validated());

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    /** GET /vehicles/{vehicleId} */
    public function show(ShowVehicleRequest $request, string $vehicleId): VehicleResource
    {
        return new VehicleResource($this->vehicles->find($vehicleId, $request->userId()));
    }

    /** PATCH /vehicles/{vehicleId} */
    public function update(UpdateVehicleRequest $request, string $vehicleId): VehicleResource
    {
        return new VehicleResource($this->vehicles->update($vehicleId, $request->userId(), $request->validated()));
    }

    /** DELETE /vehicles/{vehicleId} */
    public function destroy(ShowVehicleRequest $request, string $vehicleId): Response
    {
        $this->vehicles->delete($vehicleId, $request->userId());

        return response()->noContent();
    }
}
