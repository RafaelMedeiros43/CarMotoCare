<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMaintenanceRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenances) {}

    /** GET /vehicles/{vehicleId}/maintenances */
    public function index(Request $request, string $vehicleId): AnonymousResourceCollection
    {
        return MaintenanceResource::collection($this->maintenances->list($this->userId($request), $vehicleId));
    }

    /** POST /vehicles/{vehicleId}/maintenances */
    public function store(CreateMaintenanceRequest $request, string $vehicleId): JsonResponse
    {
        $created = $this->maintenances->create($this->userId($request), $vehicleId, $request->validated());

        // MaintenanceCreated: the maintenance plus the alerts closed by it.
        return response()->json(
            [...(new MaintenanceResource($created))->resolve($request), 'closedAlertIds' => $created['closedAlertIds'] ?? []],
            201,
        );
    }

    /** GET /vehicles/{vehicleId}/maintenances/{maintenanceId} */
    public function show(Request $request, string $vehicleId, string $maintenanceId): MaintenanceResource
    {
        return new MaintenanceResource($this->maintenances->find($this->userId($request), $vehicleId, $maintenanceId));
    }

    /** PATCH /vehicles/{vehicleId}/maintenances/{maintenanceId} */
    public function update(UpdateMaintenanceRequest $request, string $vehicleId, string $maintenanceId): MaintenanceResource
    {
        return new MaintenanceResource(
            $this->maintenances->update($this->userId($request), $vehicleId, $maintenanceId, $request->validated())
        );
    }

    /** The user id set by the auth.jwt middleware: the only source of the user. */
    private function userId(Request $request): string
    {
        return $request->attributes->get('userId');
    }
}
