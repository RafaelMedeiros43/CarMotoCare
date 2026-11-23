<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nota: não redefinir aqui métodos de RefreshDatabase (migrateFreshUsing, beforeRefreshingDatabase...).
 * Como cada teste faz "use RefreshDatabase", os métodos da trait sobrepõem-se aos desta classe e as
 * alterações eram ignoradas. Por isso a proteção fica em setUpTraits(), que corre antes do RefreshDatabase.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        // Proteção: o RefreshDatabase faz migrate:fresh (apaga tudo). Nunca na base de desenvolvimento.
        $database = DB::connection()->getDatabaseName();
        if (! str_ends_with($database, '_test_users')) {
            throw new RuntimeException(
                "Os testes iam correr na base \"{$database}\" e podiam apagá-la. Esperava-se "
                .'carmotocare_test_users (ver phpunit.xml e "Testes" no README da raiz).'
            );
        }

        return parent::setUpTraits();
    }
}
