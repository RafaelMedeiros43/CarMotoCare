<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeTest extends TestCase
{
    private const URL = 'http://sapi-users.test';

    private const SECRET = 'test-secret-with-more-than-32-characters!';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const USER = [
        'id' => self::USER_ID,
        'name' => 'Miguel Santos',
        'email' => 'miguel@carmotocare.pt',
        'createdAt' => '2026-11-06T10:48:00Z',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sapi_users.url' => self::URL,
            'jwt.secret' => self::SECRET,
            'jwt.ttl' => 3600,
        ]);
    }

    private function bearer(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? app(JwtService::class)->issue(self::USER_ID)['token'])];
    }

    private function sapiProblem(int $status, string $title, string $detail, array $errors = []): array
    {
        return array_filter([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'errors' => $errors ?: null,
        ]);
    }

    // ---------- token validation (middleware) ----------

    public function test_request_without_token_returns_401(): void
    {
        Http::fake();

        $this->getJson('/users/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'Token ausente ou inválido.');

        Http::assertNothingSent();
    }

    public function test_malformed_token_returns_401(): void
    {
        Http::fake();

        $this->getJson('/users/me', $this->bearer('not-a-jwt'))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_token_signed_with_another_secret_returns_401(): void
    {
        $forged = JWT::encode(
            ['sub' => self::USER_ID, 'iat' => time(), 'exp' => time() + 3600],
            'another-secret-with-more-than-32-chars!!',
            'HS256',
        );

        $this->getJson('/users/me', $this->bearer($forged))->assertUnauthorized();
    }

    public function test_expired_token_returns_401(): void
    {
        $expired = JWT::encode(
            ['sub' => self::USER_ID, 'iat' => time() - 7200, 'exp' => time() - 3600],
            self::SECRET,
            'HS256',
        );

        $this->getJson('/users/me', $this->bearer($expired))->assertUnauthorized();
    }

    public function test_unsigned_token_with_alg_none_returns_401(): void
    {
        $base64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $unsigned = $base64(['alg' => 'none', 'typ' => 'JWT']).'.'
            .$base64(['sub' => self::USER_ID, 'exp' => time() + 3600]).'.';

        $this->getJson('/users/me', $this->bearer($unsigned))->assertUnauthorized();
    }

    public function test_token_without_sub_returns_401(): void
    {
        $noSub = JWT::encode(['iat' => time(), 'exp' => time() + 3600], self::SECRET, 'HS256');

        $this->getJson('/users/me', $this->bearer($noSub))->assertUnauthorized();
    }

    // ---------- GET /users/me ----------

    public function test_get_me_returns_the_account_of_the_token_user(): void
    {
        Http::fake([self::URL.'/users/'.self::USER_ID => Http::response(self::USER + ['passwordHash' => 'x'])]);

        $response = $this->getJson('/users/me', $this->bearer())
            ->assertOk()
            ->assertExactJson(self::USER);

        $this->assertStringNotContainsString('assword', $response->getContent());
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::URL.'/users/'.self::USER_ID);
    }

    public function test_get_me_with_a_token_for_a_deleted_account_returns_401(): void
    {
        Http::fake([self::URL.'/*' => Http::response($this->sapiProblem(404, 'Não encontrado', 'A conta não existe.'), 404)]);

        $this->getJson('/users/me', $this->bearer())
            ->assertUnauthorized()
            ->assertJsonPath('detail', 'Token ausente ou inválido.');
    }

    // ---------- PATCH /users/me ----------

    public function test_patch_me_updates_the_token_user_and_ignores_any_other_id(): void
    {
        Http::fake([self::URL.'/users/'.self::USER_ID => Http::response(['name' => 'Novo Nome'] + self::USER)]);

        $this->patchJson('/users/me', ['name' => 'Novo Nome', 'id' => 'someone-else'], $this->bearer())
            ->assertOk()
            ->assertJsonPath('name', 'Novo Nome');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->url() === self::URL.'/users/'.self::USER_ID
            && $r->data() === ['name' => 'Novo Nome']);
    }

    public function test_patch_me_forwards_passwords_without_trimming_them(): void
    {
        Http::fake([self::URL.'/*' => Http::response(self::USER)]);

        $this->patchJson('/users/me', [
            'newPassword' => '  novasenha99  ',
            'currentPassword' => '  segredo123  ',
        ], $this->bearer())->assertOk();

        Http::assertSent(fn (Request $r) => $r->data()['newPassword'] === '  novasenha99  '
            && $r->data()['currentPassword'] === '  segredo123  ');
    }

    public function test_wrong_current_password_returns_400_and_not_401(): void
    {
        Http::fake([self::URL.'/*' => Http::response($this->sapiProblem(401, 'Não autenticado', 'A palavra-passe atual está incorreta.'), 401)]);

        $this->patchJson('/users/me', ['email' => 'novo@carmotocare.pt', 'currentPassword' => 'errada1234'], $this->bearer())
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors', [
                ['field' => 'currentPassword', 'message' => 'A palavra-passe atual está incorreta.'],
            ]);
    }

    public function test_missing_current_password_from_the_sapi_is_passed_on_as_400(): void
    {
        Http::fake([self::URL.'/*' => Http::response($this->sapiProblem(
            400,
            'Pedido inválido',
            'Há campos inválidos.',
            [['field' => 'currentPassword', 'message' => 'Indique a palavra-passe atual para mudar o e-mail ou a palavra-passe.']],
        ), 400)]);

        $this->patchJson('/users/me', ['email' => 'novo@carmotocare.pt'], $this->bearer())
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'currentPassword');
    }

    public function test_email_used_by_another_account_returns_409(): void
    {
        Http::fake([self::URL.'/*' => Http::response($this->sapiProblem(409, 'Conflito', 'Já existe uma conta com este e-mail.'), 409)]);

        $this->patchJson('/users/me', ['email' => 'outro@carmotocare.pt', 'currentPassword' => 'segredo123'], $this->bearer())
            ->assertConflict()
            ->assertJsonPath('detail', 'Já existe uma conta com este e-mail.');
    }

    public function test_patch_me_validates_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->patchJson('/users/me', [], $this->bearer())->assertStatus(400);
        $this->patchJson('/users/me', ['currentPassword' => 'segredo123'], $this->bearer())->assertStatus(400);
        $this->patchJson('/users/me', ['name' => '', 'email' => 'not-an-email', 'newPassword' => 'curta'], $this->bearer())
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'name', 'message' => 'O nome é obrigatório.'],
                ['field' => 'email', 'message' => 'O e-mail não é válido.'],
                ['field' => 'newPassword', 'message' => 'A nova palavra-passe tem de ter pelo menos 8 caracteres.'],
            ]);

        Http::assertNothingSent();
    }

    public function test_patch_me_without_token_returns_401(): void
    {
        Http::fake();

        $this->patchJson('/users/me', ['name' => 'X'])->assertUnauthorized();

        Http::assertNothingSent();
    }
}
