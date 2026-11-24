<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Miguel Santos',
            'email' => 'miguel@carmotocare.pt',
            'password' => 'segredo123',
        ], $overrides);
    }

    public function test_creates_account_and_returns_201(): void
    {
        $response = $this->postJson('/users', $this->validPayload())
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('name', 'Miguel Santos')
            ->assertJsonPath('email', 'miguel@carmotocare.pt');

        $this->assertSame(['id', 'name', 'email', 'createdAt'], array_keys($response->json()));
        $this->assertTrue(Str::isUuid($response->json('id')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('createdAt'));

        $user = User::findOrFail($response->json('id'));
        $this->assertTrue(Hash::check('segredo123', $user->password_hash));
    }

    public function test_response_never_contains_the_password(): void
    {
        $content = $this->postJson('/users', $this->validPayload())->assertCreated()->getContent();

        $this->assertStringNotContainsString('segredo123', $content);
        $this->assertStringNotContainsString('password', $content);
    }

    public function test_email_is_stored_in_lowercase_without_spaces(): void
    {
        $this->postJson('/users', $this->validPayload(['email' => '  Miguel@CarMotoCare.PT ']))
            ->assertCreated()
            ->assertJsonPath('email', 'miguel@carmotocare.pt');

        $this->assertDatabaseHas('users', ['email' => 'miguel@carmotocare.pt']);
    }

    public function test_duplicate_email_returns_409(): void
    {
        User::factory()->create(['email' => 'miguel@carmotocare.pt']);

        $this->postJson('/users', $this->validPayload())
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('detail', 'Já existe uma conta com este e-mail.');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_email_with_different_case_returns_409(): void
    {
        User::factory()->create(['email' => 'miguel@carmotocare.pt']);

        $this->postJson('/users', $this->validPayload(['email' => 'MIGUEL@carmotocare.pt']))
            ->assertConflict();
    }

    public function test_missing_fields_return_400_with_portuguese_messages(): void
    {
        $this->postJson('/users', [])
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors', [
                ['field' => 'name', 'message' => 'O nome é obrigatório.'],
                ['field' => 'email', 'message' => 'O e-mail é obrigatório.'],
                ['field' => 'password', 'message' => 'A palavra-passe é obrigatória.'],
            ]);
    }

    public function test_invalid_fields_return_400(): void
    {
        $this->postJson('/users', [
            'name' => str_repeat('a', 101),
            'email' => 'not-an-email',
            'password' => 'curta',
        ])
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'name', 'message' => 'O nome não pode ter mais de 100 caracteres.'],
                ['field' => 'email', 'message' => 'O e-mail não é válido.'],
                ['field' => 'password', 'message' => 'A palavra-passe tem de ter pelo menos 8 caracteres.'],
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_over_72_characters_returns_400(): void
    {
        $this->postJson('/users', $this->validPayload(['password' => str_repeat('a', 73)]))
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'password', 'message' => 'A palavra-passe não pode ter mais de 72 caracteres.'],
            ]);

        $this->postJson('/users', $this->validPayload(['password' => str_repeat('a', 72)]))->assertCreated();
    }

    public function test_blank_name_returns_400(): void
    {
        $this->postJson('/users', $this->validPayload(['name' => '   ']))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'name');
    }
}
