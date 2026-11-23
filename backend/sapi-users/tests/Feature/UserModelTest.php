<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exemplo de testes com base de dados (carmotocare_test_users). Os testes dos endpoints seguem o mesmo
 * padrão, com $this->postJson('/users', [...])->assertCreated().
 */
class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_stored_as_hash_and_never_serialized(): void
    {
        $user = User::create([
            'name' => 'Demo',
            'email' => 'demo@carmotocare.pt',
            'password_hash' => 'demo1234',
        ]);

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertNotSame('demo1234', $user->password_hash);
        $this->assertTrue(Hash::check('demo1234', $user->password_hash));
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
    }

    public function test_email_is_unique(): void
    {
        User::factory()->create(['email' => 'demo@carmotocare.pt']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'demo@carmotocare.pt']);
    }
}
