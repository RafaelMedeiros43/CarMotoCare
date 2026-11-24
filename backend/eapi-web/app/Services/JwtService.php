<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/**
 * Issues and validates the access tokens (HS256) of POST /auth/tokens.
 * Claims: sub = user id, iat = issued at, exp = expiry.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';

    /**
     * @return array{token: string, expiresIn: int}
     */
    public function issue(string $userId): array
    {
        $ttl = (int) config('jwt.ttl');
        $now = time();

        $token = JWT::encode([
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $this->secret(), self::ALGORITHM);

        return ['token' => $token, 'expiresIn' => $ttl];
    }

    /**
     * Validates the signature and expiry and returns the user id (claim "sub").
     * The algorithm is fixed to HS256, so tokens with another algorithm (e.g. "none") are refused.
     *
     * @throws ProblemException 401 if the token is invalid or expired
     */
    public function userIdFrom(string $token): string
    {
        $secret = $this->secret();

        try {
            $claims = JWT::decode($token, new Key($secret, self::ALGORITHM));
        } catch (Throwable) {
            // Malformed, wrong signature, expired... the browser gets the same answer for all of them.
            throw $this->invalidToken();
        }

        if (! isset($claims->sub) || ! is_string($claims->sub) || $claims->sub === '') {
            throw $this->invalidToken();
        }

        return $claims->sub;
    }

    private function secret(): string
    {
        $secret = config('jwt.secret');

        if (! is_string($secret) || $secret === '') {
            // A configuration error, not caused by the user: it becomes a generic 500 and is logged.
            throw new RuntimeException('JWT_SECRET is not set.');
        }

        return $secret;
    }

    private function invalidToken(): ProblemException
    {
        return new ProblemException(401, 'Não autenticado', 'Token ausente ou inválido.');
    }
}
