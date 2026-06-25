<?php

namespace App\Providers;

use App\Repositories\Glpi\ApiGlpiDirectoryRepository;
use App\Repositories\Glpi\ApiGlpiInventoryRepository;
use App\Repositories\Glpi\ApiGlpiPlanningRepository;
use App\Repositories\Glpi\ApiGlpiTicketRepository;
use App\Repositories\Glpi\FakeGlpiDirectoryRepository;
use App\Repositories\Glpi\FakeGlpiInventoryRepository;
use App\Repositories\Glpi\FakeGlpiPlanningRepository;
use App\Repositories\Glpi\FakeGlpiTicketRepository;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use App\Repositories\Glpi\GlpiPlanningRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use App\Repositories\Zabbix\ApiZabbixRepository;
use App\Repositories\Zabbix\FakeZabbixRepository;
use App\Repositories\Zabbix\ZabbixRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Liga a interface à implementação correta conforme GLPI_DRIVER.
     * Este é o ÚNICO ponto do app que conhece as classes concretas.
     */
    public function register(): void
    {
        $this->app->bind(GlpiTicketRepositoryInterface::class, function () {
            $driver = config('glpi.driver', 'fake');

            return match ($driver) {
                'fake' => new FakeGlpiTicketRepository(),
                'api' => new ApiGlpiTicketRepository(
                    apiUrl: (string) config('glpi.api.url'),
                    appToken: (string) config('glpi.api.app_token'),
                    user: (string) config('glpi.api.user'),
                    password: (string) config('glpi.api.password'),
                ),
                default => throw new InvalidArgumentException(
                    "GLPI_DRIVER inválido: '{$driver}'. Use 'fake' ou 'api'."
                ),
            };
        });

        // Agenda interna — mesma flag GLPI_DRIVER decide Fake vs Api.
        $this->app->bind(GlpiPlanningRepositoryInterface::class, function () {
            $driver = config('glpi.driver', 'fake');

            return match ($driver) {
                'fake' => new FakeGlpiPlanningRepository(),
                'api' => new ApiGlpiPlanningRepository(
                    apiUrl: (string) config('glpi.api.url'),
                    appToken: (string) config('glpi.api.app_token'),
                    user: (string) config('glpi.api.user'),
                    password: (string) config('glpi.api.password'),
                ),
                default => throw new InvalidArgumentException(
                    "GLPI_DRIVER inválido: '{$driver}'. Use 'fake' ou 'api'."
                ),
            };
        });

        // Monitoramento (Zabbix) — aba Dashboards.
        $this->app->bind(ZabbixRepositoryInterface::class, function () {
            return match (config('zabbix.driver', 'api')) {
                'fake' => new FakeZabbixRepository(),
                default => new ApiZabbixRepository(
                    url: (string) config('zabbix.api.url'),
                    user: (string) config('zabbix.api.user'),
                    password: (string) config('zabbix.api.password'),
                ),
            };
        });

        // Inventário (ativos do GLPI) — tela Inventário.
        $this->app->bind(GlpiInventoryRepositoryInterface::class, function () {
            $driver = config('glpi.driver', 'fake');

            return match ($driver) {
                'fake' => new FakeGlpiInventoryRepository(),
                'api' => new ApiGlpiInventoryRepository(
                    apiUrl: (string) config('glpi.api.url'),
                    appToken: (string) config('glpi.api.app_token'),
                    user: (string) config('glpi.api.user'),
                    password: (string) config('glpi.api.password'),
                ),
                default => throw new InvalidArgumentException(
                    "GLPI_DRIVER inválido: '{$driver}'. Use 'fake' ou 'api'."
                ),
            };
        });

        // Diretório (entidades/perfis/acessos) — tela Clientes.
        $this->app->bind(GlpiDirectoryRepositoryInterface::class, function () {
            $driver = config('glpi.driver', 'fake');

            return match ($driver) {
                'fake' => new FakeGlpiDirectoryRepository(),
                'api' => new ApiGlpiDirectoryRepository(
                    apiUrl: (string) config('glpi.api.url'),
                    appToken: (string) config('glpi.api.app_token'),
                    user: (string) config('glpi.api.user'),
                    password: (string) config('glpi.api.password'),
                ),
                default => throw new InvalidArgumentException(
                    "GLPI_DRIVER inválido: '{$driver}'. Use 'fake' ou 'api'."
                ),
            };
        });
    }
}
