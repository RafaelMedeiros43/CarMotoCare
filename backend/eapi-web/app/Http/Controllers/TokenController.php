<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTokenRequest;
use App\Http\Resources\TokenResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class TokenController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    /** POST /auth/tokens */
    public function store(CreateTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        return (new TokenResource($this->accounts->login($data['email'], $data['password'])))
            ->response()
            ->setStatusCode(201);
    }
}
