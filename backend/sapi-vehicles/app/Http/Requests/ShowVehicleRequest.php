<?php

namespace App\Http\Requests;

/** GET and DELETE /vehicles/{vehicleId}: only the optional X-User-Id header. */
class ShowVehicleRequest extends UserScopedRequest {}
