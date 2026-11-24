<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMaintenanceRequest;
use App\Http\Requests\ShowVehicleRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenances) {}

    /** GET /vehicles/{vehicleId}/maintenances */
    public function index(ShowVehicleRequest $request, string $vehicleId): AnonymousResourceCollection
    {
        return MaintenanceResource::collection($this->maintenances->list($vehicleId, $request->userId()));
    }

    /** POST /vehicles/{vehicleId}/maintenances */
    public function store(CreateMaintenanceRequest $request, string $vehicleId): JsonResponse
    {
        [$maintenance, $closedAlertIds] = $this->maintenances->create($vehicleId, $request->userId(), $request->validated());

        // MaintenanceCreated: the maintenance plus the alerts closed by it.
        return response()->json(
            [...(new MaintenanceResource($maintenance))->resolve($request), 'closedAlertIds' => $closedAlertIds],
            201,
        );
    }

    /** GET /vehicles/{vehicleId}/maintenances/{maintenanceId} */
    public function show(ShowVehicleRequest $request, string $vehicleId, string $maintenanceId): MaintenanceResource
    {
        return new MaintenanceResource($this->maintenances->find($vehicleId, $maintenanceId, $request->userId()));
    }

    /** PATCH /vehicles/{vehicleId}/maintenances/{maintenanceId} */
    public function update(UpdateMaintenanceRequest $request, string $vehicleId, string $maintenanceId): MaintenanceResource
    {
        return new MaintenanceResource(
            $this->maintenances->update($vehicleId, $maintenanceId, $request->userId(), $request->validated())
        );
    }
}
