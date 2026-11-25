<?php

namespace App\Http\Controllers;

use App\Http\Resources\PartResource;
use App\Services\PartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    public function __construct(private readonly PartService $parts) {}

    /** GET /vehicles/{vehicleId}/parts */
    public function index(Request $request, string $vehicleId): AnonymousResourceCollection
    {
        return PartResource::collection(
            $this->parts->listForVehicle($request->attributes->get('userId'), $vehicleId)
        );
    }
}
