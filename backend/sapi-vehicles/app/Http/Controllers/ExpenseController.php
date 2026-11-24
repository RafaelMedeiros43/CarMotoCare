<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowVehicleRequest;
use App\Http\Resources\ExpenseResource;
use App\Services\ExpenseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    /** GET /vehicles/{vehicleId}/expenses */
    public function index(ShowVehicleRequest $request, string $vehicleId): AnonymousResourceCollection
    {
        return ExpenseResource::collection($this->expenses->listForVehicle($vehicleId, $request->userId()));
    }
}
