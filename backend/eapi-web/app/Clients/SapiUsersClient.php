<?php

namespace App\Clients;

use App\Exceptions\ProblemException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the sapi-users System API (docs/api/sapi-users.yaml).
 *
 * Problem responses from the SAPI (400, 401, 404, 409) are rethrown as ProblemException with the
 * same status, title, detail and field errors, so the browser sees the SAPI's message.
 * Anything else (SAPI down, timeout, 5xx) becomes a 502.
 */
class SapiUsersClient
{
    /** Statuses the SAPI uses for business errors; they are passed on to the browser as they are. */
    private const PASSTHROUGH_STATUSES = [400, 401, 404, 409];

    /**
     * POST /users
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function createUser(array $data): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post('/users', $data));
    }

    /**
     * POST /authentications
     *
     * @return array{userId: string, name: string, email: string}
     */
    public function verifyCredentials(string $email, string $password): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post('/authentications', [
            'email' => $email,
            'password' => $password,
        ]));
    }

    /**
     * GET /users/{userId}
     *
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function getUser(string $userId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('/users/'.rawurlencode($userId)));
    }

    /**
     * PATCH /users/{userId}
     *
     * @param  array{name?: string, email?: string, newPassword?: string, currentPassword?: string}  $data
     * @return array{id: string, name: string, email: string, createdAt: ?string}
     */
    public function updateUser(string $userId, array $data): array
    {
        return $this->send(fn (PendingRequest $http) => $http->patch('/users/'.rawurlencode($userId), $data));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = Http::baseUrl(rtrim((string) config('services.sapi_users.url'), '/'))
            ->acceptJson()
            ->connectTimeout(2)
            ->timeout(5);

        try {
            $response = $call($http);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if (in_array($response->status(), self::PASSTHROUGH_STATUSES, true)) {
            $problem = $response->json();
            $problem = is_array($problem) ? $problem : [];

            throw new ProblemException(
                $response->status(),
                (string) ($problem['title'] ?? 'Erro'),
                isset($problem['detail']) ? (string) $problem['detail'] : null,
                is_array($problem['errors'] ?? null) ? $problem['errors'] : [],
            );
        }

        // 5xx or an unexpected status: the SAPI is not working as its contract says.
        report("sapi-users returned an unexpected status {$response->status()}");

        throw $this->unavailable();
    }

    private function unavailable(): ProblemException
    {
        return new ProblemException(
            502,
            'Serviço indisponível',
            'O serviço de utilizadores não está disponível. Tente novamente dentro de instantes.',
        );
    }
}
