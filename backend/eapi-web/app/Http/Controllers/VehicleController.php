<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVehicleRequest;
use App\Http\Requests\ListVehiclesRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    /** GET /vehicles */
    public function index(ListVehiclesRequest $request): JsonResponse
    {
        $result = $this->vehicles->list($this->userId($request), $request->validated());

        return VehicleResource::collection($result['vehicles'])
            ->response()
            ->header('X-Total-Count', $result['total']);
    }

    /** POST /vehicles */
    public function store(CreateVehicleRequest $request): JsonResponse
    {
        $vehicle = $this->vehicles->create($this->userId($request), $request->validated());

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    /** GET /vehicles/{vehicleId} */
    public function show(Request $request, string $vehicleId): VehicleResource
    {
        return new VehicleResource($this->vehicles->find($this->userId($request), $vehicleId));
    }

    /** PATCH /vehicles/{vehicleId} */
    public function update(UpdateVehicleRequest $request, string $vehicleId): VehicleResource
    {
        return new VehicleResource($this->vehicles->update($this->userId($request), $vehicleId, $request->validated()));
    }

    /** DELETE /vehicles/{vehicleId} */
    public function destroy(Request $request, string $vehicleId): Response
    {
        $this->vehicles->delete($this->userId($request), $vehicleId);

        return response()->noContent();
    }

    /** The user id set by the auth.jwt middleware: the only source of the user. */
    private function userId(Request $request): string
    {
        return $request->attributes->get('userId');
    }
}
