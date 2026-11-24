<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowAndUpdateUserTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Miguel Santos',
            'email' => 'miguel@carmotocare.pt',
            'password_hash' => 'segredo123',
        ], $overrides));
    }

    // ---------- GET ----------

    public function test_show_returns_the_account(): void
    {
        $user = $this->createUser();

        $response = $this->getJson("/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'miguel@carmotocare.pt');

        $this->assertSame(['id', 'name', 'email', 'createdAt'], array_keys($response->json()));
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $this->getJson('/users/'.Str::uuid())
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_show_malformed_id_returns_404(): void
    {
        $this->getJson('/users/not-a-uuid')->assertNotFound();
    }

    // ---------- PATCH ----------

    public function test_name_can_be_changed_without_current_password(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", ['name' => 'Miguel S.'])
            ->assertOk()
            ->assertJsonPath('name', 'Miguel S.');

        $this->assertSame('Miguel S.', $user->fresh()->name);
    }

    public function test_email_can_be_changed_with_current_password(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'email' => 'Novo@CarMotoCare.pt',
            'currentPassword' => 'segredo123',
        ])->assertOk()->assertJsonPath('email', 'novo@carmotocare.pt');

        $this->assertSame('novo@carmotocare.pt', $user->fresh()->email);
    }

    public function test_same_email_is_not_a_change_and_needs_no_current_password(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", ['email' => 'MIGUEL@carmotocare.pt', 'name' => 'Outro'])
            ->assertOk();
    }

    public function test_password_can_be_changed_with_current_password(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'newPassword' => 'novasenha99',
            'currentPassword' => 'segredo123',
        ])->assertOk();

        $this->assertTrue(Hash::check('novasenha99', $user->fresh()->password_hash));
        $this->postJson('/authentications', ['email' => 'miguel@carmotocare.pt', 'password' => 'segredo123'])
            ->assertUnauthorized();
    }

    public function test_new_password_keeps_surrounding_spaces(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'newPassword' => '  espacos99  ',
            'currentPassword' => 'segredo123',
        ])->assertOk();

        $this->assertTrue(Hash::check('  espacos99  ', $user->fresh()->password_hash));
    }

    public function test_changing_email_without_current_password_returns_400(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", ['email' => 'novo@carmotocare.pt'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'currentPassword');

        $this->assertSame('miguel@carmotocare.pt', $user->fresh()->email);
    }

    public function test_changing_password_without_current_password_returns_400(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", ['newPassword' => 'novasenha99'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'currentPassword');
    }

    public function test_wrong_current_password_returns_401_and_changes_nothing(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'email' => 'novo@carmotocare.pt',
            'currentPassword' => 'errada1234',
        ])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertSame('miguel@carmotocare.pt', $user->fresh()->email);
    }

    public function test_email_used_by_another_account_returns_409(): void
    {
        $user = $this->createUser();
        User::factory()->create(['email' => 'outro@carmotocare.pt']);

        $this->patchJson("/users/{$user->id}", [
            'email' => 'OUTRO@carmotocare.pt',
            'currentPassword' => 'segredo123',
        ])->assertConflict();

        $this->assertSame('miguel@carmotocare.pt', $user->fresh()->email);
    }

    public function test_empty_body_returns_400(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [])->assertStatus(400);
        $this->patchJson("/users/{$user->id}", ['currentPassword' => 'segredo123'])->assertStatus(400);
    }

    public function test_invalid_fields_return_400(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'name' => '',
            'email' => 'not-an-email',
            'newPassword' => 'curta',
        ])
            ->assertStatus(400)
            ->assertJsonPath('errors', [
                ['field' => 'name', 'message' => 'O nome é obrigatório.'],
                ['field' => 'email', 'message' => 'O e-mail não é válido.'],
                ['field' => 'newPassword', 'message' => 'A nova palavra-passe tem de ter pelo menos 8 caracteres.'],
            ]);
    }

    public function test_new_password_over_72_characters_returns_400(): void
    {
        $user = $this->createUser();

        $this->patchJson("/users/{$user->id}", [
            'newPassword' => str_repeat('a', 73),
            'currentPassword' => 'segredo123',
        ])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.message', 'A nova palavra-passe não pode ter mais de 72 caracteres.');
    }

    public function test_update_unknown_id_returns_404(): void
    {
        $this->patchJson('/users/'.Str::uuid(), ['name' => 'X'])->assertNotFound();
    }

    public function test_response_never_contains_password_data(): void
    {
        $user = $this->createUser();

        $content = $this->patchJson("/users/{$user->id}", ['name' => 'Novo Nome'])->getContent();

        $this->assertStringNotContainsString('assword', $content);
    }
}
