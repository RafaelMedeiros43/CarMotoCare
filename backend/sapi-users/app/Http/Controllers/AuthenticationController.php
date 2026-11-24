<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyCredentialsRequest;
use App\Http\Resources\AuthResultResource;
use App\Services\UserService;

class AuthenticationController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** POST /authentications */
    public function store(VerifyCredentialsRequest $request): AuthResultResource
    {
        $data = $request->validated();

        return new AuthResultResource(
            $this->users->verifyCredentials($data['email'], $data['password'])
        );
    }
}
