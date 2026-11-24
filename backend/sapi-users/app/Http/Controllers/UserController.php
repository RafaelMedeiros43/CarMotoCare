<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** POST /users */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /** GET /users/{userId} */
    public function show(string $userId): UserResource
    {
        return new UserResource($this->users->find($userId));
    }

    /** PATCH /users/{userId} */
    public function update(UpdateUserRequest $request, string $userId): UserResource
    {
        return new UserResource($this->users->update($userId, $request->validated()));
    }
}
