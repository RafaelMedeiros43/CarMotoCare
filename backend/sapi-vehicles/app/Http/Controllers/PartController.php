<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowVehicleRequest;
use App\Http\Requests\UpdatePartRequest;
use App\Http\Resources\PartResource;
use App\Services\PartService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    public function __construct(private readonly PartService $parts) {}

    /** GET /vehicles/{vehicleId}/parts */
    public function index(ShowVehicleRequest $request, string $vehicleId): AnonymousResourceCollection
    {
        return PartResource::collection($this->parts->listForVehicle($vehicleId, $request->userId()));
    }

    /** PATCH /vehicles/{vehicleId}/parts/{partId} */
    public function update(UpdatePartRequest $request, string $vehicleId, string $partId): PartResource
    {
        return new PartResource($this->parts->update($vehicleId, $partId, $request->userId(), $request->validated()));
    }
}
