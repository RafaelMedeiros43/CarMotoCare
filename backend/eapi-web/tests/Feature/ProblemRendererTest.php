<?php

namespace Tests\Feature;

use App\Exceptions\ProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProblemRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Routes that only exist in this test, to trigger each kind of error.
        Route::post('/_test/validation', fn (Request $request) => $request->validate(
            ['name' => 'required', 'email' => 'required|email'],
            ['name.required' => 'O nome é obrigatório.', 'email.email' => 'O e-mail não é válido.'],
        ));
        Route::get('/_test/problem', fn () => throw new ProblemException(409, 'Conflito', 'Já existe uma conta com este e-mail.'));
        Route::get('/_test/crash', fn () => throw new RuntimeException('secret internal message'));
    }

    public function test_unknown_route_returns_404_problem(): void
    {
        $this->getJson('/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => 'about:blank',
                'title' => 'Não encontrado',
                'status' => 404,
                'detail' => 'O recurso pedido não existe.',
            ]);
    }

    public function test_validation_error_returns_400_with_field_errors(): void
    {
        $this->postJson('/_test/validation', ['email' => 'not-an-email'])
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => 'about:blank',
                'title' => 'Pedido inválido',
                'status' => 400,
                'detail' => 'Há campos inválidos.',
                'errors' => [
                    ['field' => 'name', 'message' => 'O nome é obrigatório.'],
                    ['field' => 'email', 'message' => 'O e-mail não é válido.'],
                ],
            ]);
    }

    public function test_problem_exception_keeps_its_status_and_detail(): void
    {
        $this->getJson('/_test/problem')
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => 'about:blank',
                'title' => 'Conflito',
                'status' => 409,
                'detail' => 'Já existe uma conta com este e-mail.',
            ]);
    }

    public function test_wrong_method_returns_405_problem(): void
    {
        $this->postJson('/health')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 405)
            ->assertJsonPath('title', 'Método não permitido');
    }

    public function test_unexpected_error_returns_500_without_internal_details(): void
    {
        $response = $this->getJson('/_test/crash')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => 'about:blank',
                'title' => 'Erro interno',
                'status' => 500,
                'detail' => 'Ocorreu um erro inesperado.',
            ]);

        $this->assertStringNotContainsString('secret internal message', $response->getContent());
    }
}
