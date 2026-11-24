<?php

namespace App\Services;

use App\Clients\SapiUsersClient;
use App\Exceptions\ProblemException;

/**
 * Accounts and sessions. The account data lives in the sapi-users; this service only orchestrates.
 */
class AccountService
{
    public function __construct(
        private readonly SapiUsersClient $users,
        private readonly JwtService $jwt,
    ) {}

    /**
     * Creates an account in the sapi-users (409 if the e-mail already exists).
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function register(array $data): array
    {
        return $this->users->createUser($data);
    }

    /**
     * Checks the credentials in the sapi-users (401 if wrong) and issues the access token.
     *
     * @return array{token: string, expiresIn: int}
     */
    public function login(string $email, string $password): array
    {
        $account = $this->users->verifyCredentials($email, $password);

        return $this->jwt->issue($account['userId']);
    }

    /**
     * The account of the logged-in user. A valid token for an account that no longer exists is a 401.
     *
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function me(string $userId): array
    {
        try {
            return $this->users->getUser($userId);
        } catch (ProblemException $e) {
            throw $this->sessionProblem($e);
        }
    }

    /**
     * Changes name, e-mail and/or password of the logged-in user.
     *
     * A wrong current password is answered with 400 (field "currentPassword") and not with the
     * sapi-users' 401, because the frontend treats every 401 as an expired session and logs the user out.
     *
     * @param  array{name?: string, email?: string, newPassword?: string, currentPassword?: ?string}  $data
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function updateMe(string $userId, array $data): array
    {
        try {
            return $this->users->updateUser($userId, $data);
        } catch (ProblemException $e) {
            if ($e->status === 401) {
                throw new ProblemException(400, 'Pedido inválido', 'Há campos inválidos.', [
                    ['field' => 'currentPassword', 'message' => 'A palavra-passe atual está incorreta.'],
                ]);
            }

            throw $this->sessionProblem($e);
        }
    }

    /** 404 from the sapi-users means the token belongs to an account that does not exist (401). */
    private function sessionProblem(ProblemException $e): ProblemException
    {
        if ($e->status === 404) {
            return new ProblemException(401, 'Não autenticado', 'Token ausente ou inválido.');
        }

        return $e;
    }
}
