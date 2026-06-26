<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Providers\FortifyServiceProvider;
use ReflectionMethod;
use Tests\TestCase;

/** Mapeamento perfil do GLPI -> papel do portal (config/portal.php + fallback). */
class MapRoleTest extends TestCase
{
    private function map(string $profile): UserRole
    {
        return (new ReflectionMethod(FortifyServiceProvider::class, 'mapRole'))->invoke(null, $profile);
    }

    public function test_mapeamento_por_config(): void
    {
        // Definidos em config/portal.php
        $this->assertSame(UserRole::Cliente, $this->map('Self-Service'));
        $this->assertSame(UserRole::Gestor, $this->map('Gestor'));
        $this->assertSame(UserRole::Gestor, $this->map('Técnico FL')); // decisão: técnico = gestor
        $this->assertSame(UserRole::Gestor, $this->map('Fourline'));
    }

    public function test_fallback_por_palavra_chave(): void
    {
        // Perfis NÃO listados na config caem na regra por palavra-chave.
        $this->assertSame(UserRole::Gestor, $this->map('Super-Admin'));
        $this->assertSame(UserRole::Tecnico, $this->map('Supervisor'));
        $this->assertSame(UserRole::Cliente, $this->map('Perfil Desconhecido'));
    }
}
