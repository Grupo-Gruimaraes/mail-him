<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Entra já com o segundo fator confirmado; para testar sem ele, usar be().
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $this->withSession(['two_factor.passed' => $user->getAuthIdentifier()]);

        return parent::actingAs($user, $guard);
    }
}
