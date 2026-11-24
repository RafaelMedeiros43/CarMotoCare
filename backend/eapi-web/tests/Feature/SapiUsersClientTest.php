<?php

namespace Tests\Feature;

use App\Clients\SapiUsersClient;
use App\Exceptions\ProblemException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapiUsersClientTest extends TestCase
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

        config(['services.sapi_users.url' => self::URL.'/']);
    }

    private function problem(int $status, string $title, ?string $detail = null, array $errors = []): array
    {
        return array_filter([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'errors' => $errors ?: null,
        ]);
    }

    public function test_create_user_posts_to_sapi_and_returns_the_account(): void
    {
        Http::fake([self::URL.'/users' => Http::response(self::USER, 201)]);

        $data = ['name' => 'Miguel Santos', 'email' => 'miguel@carmotocare.pt', 'password' => 'segredo123'];
        $user = (new SapiUsersClient)->createUser($data);

        $this->assertSame(self::USER, $user);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::URL.'/users'
            && $r->data() === $data);
    }

    public function test_verify_credentials_posts_email_and_password(): void
    {
        $result = ['userId' => self::USER['id'], 'name' => 'Miguel Santos', 'email' => 'miguel@carmotocare.pt'];
        Http::fake([self::URL.'/authentications' => Http::response($result)]);

        $this->assertSame($result, (new SapiUsersClient)->verifyCredentials('miguel@carmotocare.pt', 'segredo123'));
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/authentications'
            && $r->data() === ['email' => 'miguel@carmotocare.pt', 'password' => 'segredo123']);
    }

    public function test_get_and_update_user_use_the_user_id_in_the_path(): void
    {
        Http::fake([self::URL.'/users/*' => Http::response(self::USER)]);
        $client = new SapiUsersClient;

        $this->assertSame(self::USER, $client->getUser(self::USER['id']));
        $this->assertSame(self::USER, $client->updateUser(self::USER['id'], ['name' => 'Novo']));

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::URL.'/users/'.self::USER['id']);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->url() === self::URL.'/users/'.self::USER['id']
            && $r->data() === ['name' => 'Novo']);
    }

    public function test_business_errors_are_passed_on_with_status_detail_and_field_errors(): void
    {
        $errors = [['field' => 'email', 'message' => 'O e-mail não é válido.']];

        // Http::fake() keeps the first matching stub, so the response is read from $next on each call.
        $next = null;
        Http::fake(function () use (&$next) {
            return $next;
        });

        foreach ([
            [400, 'Pedido inválido', 'Há campos inválidos.', $errors],
            [401, 'Não autenticado', 'E-mail ou palavra-passe incorretos.', []],
            [404, 'Não encontrado', 'A conta não existe.', []],
            [409, 'Conflito', 'Já existe uma conta com este e-mail.', []],
        ] as [$status, $title, $detail, $fieldErrors]) {
            $next = Http::response($this->problem($status, $title, $detail, $fieldErrors), $status);

            try {
                (new SapiUsersClient)->getUser(self::USER['id']);
                $this->fail("Expected a ProblemException for {$status}");
            } catch (ProblemException $e) {
                $this->assertSame($status, $e->status);
                $this->assertSame($title, $e->title);
                $this->assertSame($detail, $e->detail);
                $this->assertSame($fieldErrors, $e->errors);
            }
        }
    }

    public function test_connection_failure_becomes_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        try {
            (new SapiUsersClient)->getUser(self::USER['id']);
            $this->fail('Expected a ProblemException');
        } catch (ProblemException $e) {
            $this->assertSame(502, $e->status);
            $this->assertSame('Serviço indisponível', $e->title);
            $this->assertStringNotContainsString('refused', (string) $e->detail);
        }
    }

    public function test_unexpected_sapi_status_becomes_502(): void
    {
        Http::fake([self::URL.'/*' => Http::response('boom', 500)]);

        try {
            (new SapiUsersClient)->getUser(self::USER['id']);
            $this->fail('Expected a ProblemException');
        } catch (ProblemException $e) {
            $this->assertSame(502, $e->status);
        }
    }

    public function test_sapi_error_without_problem_body_is_still_passed_on(): void
    {
        Http::fake([self::URL.'/*' => Http::response('not json', 404)]);

        try {
            (new SapiUsersClient)->getUser(self::USER['id']);
            $this->fail('Expected a ProblemException');
        } catch (ProblemException $e) {
            $this->assertSame(404, $e->status);
        }
    }
}
