<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Creates an account. The e-mail must be unique (409 otherwise).
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User
    {
        $email = mb_strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            throw $this->emailTaken();
        }

        try {
            return User::create([
                'name' => $data['name'],
                'email' => $email,
                'password_hash' => $data['password'], // hashed by the model cast
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same e-mail at the same time: the database has the final word.
            throw $this->emailTaken();
        }
    }

    /**
     * Finds an account by id. A malformed id is just another id that does not exist (404).
     */
    public function find(string $id): User
    {
        $user = Str::isUuid($id) ? User::find($id) : null;

        return $user ?? throw new ProblemException(404, 'Não encontrado', 'A conta não existe.');
    }

    /**
     * Updates name, e-mail and/or password. Changing the e-mail or the password needs
     * the current password (400 if missing, 401 if wrong). The new e-mail must be unique (409).
     *
     * @param  array{name?: string, email?: string, newPassword?: string, currentPassword?: ?string}  $data
     */
    public function update(string $id, array $data): User
    {
        $user = $this->find($id);

        $newEmail = isset($data['email']) ? mb_strtolower($data['email']) : null;
        $emailChanges = $newEmail !== null && $newEmail !== $user->email;
        $passwordChanges = isset($data['newPassword']);

        if ($emailChanges || $passwordChanges) {
            $current = $data['currentPassword'] ?? null;

            if ($current === null || $current === '') {
                throw new ProblemException(400, 'Pedido inválido', 'Há campos inválidos.', [
                    ['field' => 'currentPassword', 'message' => 'Indique a palavra-passe atual para mudar o e-mail ou a palavra-passe.'],
                ]);
            }

            if (! Hash::check($current, $user->password_hash)) {
                throw new ProblemException(401, 'Não autenticado', 'A palavra-passe atual está incorreta.');
            }
        }

        if ($emailChanges && User::where('email', $newEmail)->whereKeyNot($user->id)->exists()) {
            throw $this->emailTaken();
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if ($emailChanges) {
            $user->email = $newEmail;
        }
        if ($passwordChanges) {
            $user->password_hash = $data['newPassword']; // hashed by the model cast
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->emailTaken();
        }

        return $user;
    }

    /**
     * Checks e-mail and password. An unknown e-mail and a wrong password give the same 401,
     * so the response does not reveal which e-mails have an account.
     */
    public function verifyCredentials(string $email, string $password): User
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if ($user === null) {
            // Same hashing work as for a real account, so the response time does not reveal it either.
            Hash::check($password, self::dummyHash());
            throw $this->invalidCredentials();
        }

        if (! Hash::check($password, $user->password_hash)) {
            throw $this->invalidCredentials();
        }

        return $user;
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('carmotocare-dummy-password');
    }

    private function invalidCredentials(): ProblemException
    {
        return new ProblemException(401, 'Não autenticado', 'E-mail ou palavra-passe incorretos.');
    }

    private function emailTaken(): ProblemException
    {
        return new ProblemException(409, 'Conflito', 'Já existe uma conta com este e-mail.');
    }
}
