<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create([
            'name' => 'Miguel Santos',
            'email' => 'miguel@carmotocare.pt',
            'password_hash' => 'segredo123',
        ]);
    }

    public function test_valid_credentials_return_200_with_account_data(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/authentications', [
            'email' => 'miguel@carmotocare.pt',
            'password' => 'segredo123',
        ])
            ->assertOk()
            ->assertExactJson([
                'userId' => $user->id,
                'name' => 'Miguel Santos',
                'email' => 'miguel@carmotocare.pt',
            ]);

        $this->assertStringNotContainsString('segredo123', $response->getContent());
    }

    public function test_email_is_matched_ignoring_case_and_spaces(): void
    {
        $this->createUser();

        $this->postJson('/authentications', [
            'email' => '  MIGUEL@CarMotoCare.pt ',
            'password' => 'segredo123',
        ])->assertOk()->assertJsonPath('email', 'miguel@carmotocare.pt');
    }

    public function test_wrong_password_and_unknown_email_return_the_same_401(): void
    {
        $this->createUser();

        $wrongPassword = $this->postJson('/authentications', [
            'email' => 'miguel@carmotocare.pt',
            'password' => 'errada1234',
        ])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'E-mail ou palavra-passe incorretos.');

        $unknownEmail = $this->postJson('/authentications', [
            'email' => 'ninguem@carmotocare.pt',
            'password' => 'segredo123',
        ])->assertUnauthorized();

        $this->assertSame($wrongPassword->getContent(), $unknownEmail->getContent());
    }

    public function test_missing_fields_return_400(): void
    {
        $this->postJson('/authentications', [])
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'email', 'message' => 'O e-mail é obrigatório.'],
                ['field' => 'password', 'message' => 'A palavra-passe é obrigatória.'],
            ]);
    }

    public function test_invalid_email_returns_400(): void
    {
        $this->postJson('/authentications', ['email' => 'not-an-email', 'password' => 'x'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'email');
    }
}
