<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nota: não redefinir aqui métodos de RefreshDatabase (migrateFreshUsing, beforeRefreshingDatabase...).
 * Como cada teste faz "use RefreshDatabase", os métodos da trait sobrepõem-se aos desta classe e as
 * alterações eram ignoradas. Por isso tudo é feito em setUpTraits(), que corre antes do RefreshDatabase.
 */
abstract class TestCase extends BaseTestCase
{
    /** A base tem a view expenses; o migrate:fresh dos testes também a apaga. */
    protected bool $dropViews = true;

    protected function setUpTraits()
    {
        // Proteção: o RefreshDatabase faz migrate:fresh (apaga tudo). Nunca na base de desenvolvimento.
        $database = DB::connection()->getDatabaseName();
        if (! str_ends_with($database, '_test_vehicles')) {
            throw new RuntimeException(
                "Os testes iam correr na base \"{$database}\" e podiam apagá-la. Esperava-se "
                .'carmotocare_test_vehicles (ver phpunit.xml e "Testes" no README da raiz).'
            );
        }

        // tests/database/migrations tem a tabela users, que em produção é criada pela sapi-users (D3); sem ela
        // a FK vehicles.user_id falhava. O nome do ficheiro (0001_01_01_...) faz com que corra primeiro.
        // Só se aplica aos testes: o "php artisan migrate" normal não vê esta pasta.
        $this->app->make('migrator')->path(base_path('tests/database/migrations'));

        return parent::setUpTraits();
    }
}
