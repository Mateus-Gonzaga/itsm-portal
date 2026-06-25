<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Providers\FortifyServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Mapeamento perfil do GLPI -> papel do portal (login). */
class MapRoleTest extends TestCase
{
    private function map(string $profile): UserRole
    {
        $m = new ReflectionMethod(FortifyServiceProvider::class, 'mapRole');

        return $m->invoke(null, $profile);
    }

    public function test_perfis_de_gestor(): void
    {
        $this->assertSame(UserRole::Gestor, $this->map('Gestor - Clientes'));
        $this->assertSame(UserRole::Gestor, $this->map('Super-Admin'));
        $this->assertSame(UserRole::Gestor, $this->map('Admin'));
    }

    public function test_perfis_de_tecnico(): void
    {
        $this->assertSame(UserRole::Tecnico, $this->map('Técnico FL'));
        $this->assertSame(UserRole::Tecnico, $this->map('Technician'));
        $this->assertSame(UserRole::Tecnico, $this->map('Supervisor'));
    }

    public function test_perfil_de_cliente_padrao(): void
    {
        $this->assertSame(UserRole::Cliente, $this->map('Self-Service'));
        $this->assertSame(UserRole::Cliente, $this->map('Qualquer Outro'));
    }
}
