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

];
