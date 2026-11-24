<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateTokenTest extends TestCase
{
    private const URL = 'http://sapi-users.test';

    private const SECRET = 'test-secret-with-more-than-32-characters!';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sapi_users.url' => self::URL,
            'jwt.secret' => self::SECRET,
            'jwt.ttl' => 3600,
        ]);
    }

    private function credentials(): array
    {
        return ['email' => 'miguel@carmotocare.pt', 'password' => 'segredo123'];
    }

    private function fakeValidLogin(): void
    {
        Http::fake([self::URL.'/authentications' => Http::response([
            'userId' => self::USER_ID,
            'name' => 'Miguel Santos',
            'email' => 'miguel@carmotocare.pt',
        ])]);
    }

    public function test_valid_credentials_return_201_with_a_bearer_token(): void
    {
        $this->fakeValidLogin();

        $response = $this->postJson('/auth/tokens', $this->credentials())
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('tokenType', 'Bearer')
            ->assertJsonPath('expiresIn', 3600);

        $this->assertSame(['accessToken', 'tokenType', 'expiresIn'], array_keys($response->json()));
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/authentications'
            && $r->data() === $this->credentials());
    }

    public function test_token_is_signed_with_the_secret_and_carries_the_user_id_and_expiry(): void
    {
        $this->fakeValidLogin();

        $before = time();
        $token = $this->postJson('/auth/tokens', $this->credentials())->json('accessToken');

        $claims = JWT::decode($token, new Key(self::SECRET, 'HS256'));

        $this->assertSame(self::USER_ID, $claims->sub);
        $this->assertSame($claims->iat + 3600, $claims->exp);
        $this->assertGreaterThanOrEqual($before, $claims->iat);
        $this->assertObjectNotHasProperty('email', $claims);
    }

    public function test_token_does_not_validate_with_another_secret(): void
    {
        $this->fakeValidLogin();

        $token = $this->postJson('/auth/tokens', $this->credentials())->json('accessToken');

        $this->expectException(SignatureInvalidException::class);
        JWT::decode($token, new Key('another-secret-with-more-than-32-chars!!', 'HS256'));
    }

    public function test_expires_in_follows_the_configured_ttl(): void
    {
        config(['jwt.ttl' => 120]);
        $this->fakeValidLogin();

        $this->postJson('/auth/tokens', $this->credentials())
            ->assertCreated()
            ->assertJsonPath('expiresIn', 120);
    }

    public function test_wrong_credentials_return_401_without_a_token(): void
    {
        Http::fake([self::URL.'/authentications' => Http::response([
            'type' => 'about:blank',
            'title' => 'Não autenticado',
            'status' => 401,
            'detail' => 'E-mail ou palavra-passe incorretos.',
        ], 401)]);

        $response = $this->postJson('/auth/tokens', $this->credentials())
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'E-mail ou palavra-passe incorretos.');

        $this->assertStringNotContainsString('accessToken', $response->getContent());
    }

    public function test_invalid_request_returns_400_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->postJson('/auth/tokens', [])
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'email', 'message' => 'O e-mail é obrigatório.'],
                ['field' => 'password', 'message' => 'A palavra-passe é obrigatória.'],
            ]);

        Http::assertNothingSent();
    }

    public function test_sapi_down_returns_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postJson('/auth/tokens', $this->credentials())
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_missing_jwt_secret_returns_500_without_details(): void
    {
        config(['jwt.secret' => null]);
        $this->fakeValidLogin();

        $response = $this->postJson('/auth/tokens', $this->credentials())
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertStringNotContainsString('JWT_SECRET', $response->getContent());
    }
}
