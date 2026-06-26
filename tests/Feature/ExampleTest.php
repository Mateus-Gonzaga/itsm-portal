<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_a_tela_de_login_carrega(): void
    {
        // '/' redireciona para o dashboard (e, sem login, para /login).
        $this->get('/')->assertRedirect();

        // A tela de login responde normalmente.
        $this->get('/login')->assertOk();
    }
}
