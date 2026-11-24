<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    /** POST /users */
    public function store(RegisterUserRequest $request): JsonResponse
    {
        $user = $this->accounts->register($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
