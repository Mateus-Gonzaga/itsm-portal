<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver do repositório de chamados
    |--------------------------------------------------------------------------
    |
    | Define qual implementação de GlpiTicketRepositoryInterface o app usa:
    |   'fake' -> FakeGlpiTicketRepository (mock em memória, Fase 1)
    |   'api'  -> ApiGlpiTicketRepository  (API REST do GLPI, Fase 2+)
    |
    | A troca é feita SÓ por esta flag, sem mexer em nenhum controller/view.
    |
    */

    'driver' => env('GLPI_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Credenciais da API GLPI (usadas apenas quando driver = 'api')
    |--------------------------------------------------------------------------
    */

    'api' => [
        'url' => env('GLPI_API_URL'),
        'app_token' => env('GLPI_APP_TOKEN'),
        'user' => env('GLPI_API_USER'),
        'password' => env('GLPI_API_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuso horário do banco do GLPI (o Docker/MariaDB opera em UTC por padrão)
    |--------------------------------------------------------------------------
    */
    'timezone' => env('GLPI_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Cache das leituras da API (desempenho)
    |--------------------------------------------------------------------------
    |
    | Guarda por pouco tempo as leituras do GLPI (chamados, agenda, diretório,
    | inventário) e reaproveita o token da conta de serviço. Toda escrita feita
    | pelo portal invalida o grupo afetado na hora. Mudanças feitas direto no
    | GLPI aparecem em até ~1–2 min. GLPI_CACHE=false desliga.
    |
    */
    'cache' => env('GLPI_CACHE', true),

];
