<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterUserTest extends TestCase
{
    private const URL = 'http://sapi-users.test';

    private const USER = [
        'id' => '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11',
        'name' => 'Miguel Santos',
        'email' => 'miguel@carmotocare.pt',
        'createdAt' => '2026-11-06T10:48:00Z',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sapi_users.url' => self::URL]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Miguel Santos',
            'email' => 'miguel@carmotocare.pt',
            'password' => 'segredo123',
        ], $overrides);
    }

    public function test_creates_account_through_the_sapi_and_returns_201(): void
    {
        // The SAPI answers with an extra field that must not reach the browser.
        Http::fake([self::URL.'/users' => Http::response(self::USER + ['passwordHash' => 'x'], 201)]);

        $response = $this->postJson('/users', $this->payload())
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(self::USER);

        $this->assertStringNotContainsString('assword', $response->getContent());
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/users'
            && $r->data() === $this->payload());
    }

    public function test_password_keeps_surrounding_spaces_when_forwarded(): void
    {
        Http::fake([self::URL.'/users' => Http::response(self::USER, 201)]);

        $this->postJson('/users', $this->payload(['password' => '  segredo123  ']))->assertCreated();

        Http::assertSent(fn (Request $r) => $r->data()['password'] === '  segredo123  ');
    }

    public function test_duplicate_email_from_the_sapi_returns_409(): void
    {
        Http::fake([self::URL.'/users' => Http::response([
            'type' => 'about:blank',
            'title' => 'Conflito',
            'status' => 409,
            'detail' => 'Já existe uma conta com este e-mail.',
        ], 409)]);

        $this->postJson('/users', $this->payload())
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'Já existe uma conta com este e-mail.');
    }

    public function test_invalid_request_returns_400_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->postJson('/users', ['name' => '', 'email' => 'not-an-email', 'password' => 'curta'])
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors', [
                ['field' => 'name', 'message' => 'O nome é obrigatório.'],
                ['field' => 'email', 'message' => 'O e-mail não é válido.'],
                ['field' => 'password', 'message' => 'A palavra-passe tem de ter pelo menos 8 caracteres.'],
            ]);

        Http::assertNothingSent();
    }

    public function test_password_over_72_characters_returns_400(): void
    {
        Http::fake();

        $this->postJson('/users', $this->payload(['password' => str_repeat('a', 73)]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'password');

        Http::assertNothingSent();
    }

    public function test_sapi_validation_error_is_passed_on_with_field_errors(): void
    {
        Http::fake([self::URL.'/users' => Http::response([
            'title' => 'Pedido inválido',
            'status' => 400,
            'detail' => 'Há campos inválidos.',
            'errors' => [['field' => 'email', 'message' => 'O e-mail não é válido.']],
        ], 400)]);

        $this->postJson('/users', $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('errors', [['field' => 'email', 'message' => 'O e-mail não é válido.']]);
    }

    public function test_sapi_down_returns_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postJson('/users', $this->payload())
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('title', 'Serviço indisponível');
    }
}
