<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseResource;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenances) {}

    /** GET /vehicles/{vehicleId}/expenses (no filters on the server: the period is filtered in the browser) */
    public function index(Request $request, string $vehicleId): AnonymousResourceCollection
    {
        return ExpenseResource::collection(
            $this->maintenances->expenses($request->attributes->get('userId'), $vehicleId)
        );
    }
}
