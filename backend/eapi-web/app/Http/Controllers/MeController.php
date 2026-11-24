<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Services\AccountService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    /** GET /users/me */
    public function show(Request $request): UserResource
    {
        return new UserResource($this->accounts->me($request->attributes->get('userId')));
    }

    /** PATCH /users/me */
    public function update(UpdateMeRequest $request): UserResource
    {
        return new UserResource(
            $this->accounts->updateMe($request->attributes->get('userId'), $request->validated())
        );
    }
}
