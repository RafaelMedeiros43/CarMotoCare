<?php

namespace App\Http\Middleware;

use App\Exceptions\ProblemException;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires "Authorization: Bearer <jwt>". On success the user id from the token is available
 * as $request->attributes->get('userId'); it is the only source of the user id (never the body or URL),
 * and it is what the EAPI sends to the SAPIs as X-User-Id.
 */
class AuthenticateJwt
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            throw new ProblemException(401, 'Não autenticado', 'Token ausente ou inválido.');
        }

        $request->attributes->set('userId', $this->jwt->userIdFrom($token));

        return $next($request);
    }
}
