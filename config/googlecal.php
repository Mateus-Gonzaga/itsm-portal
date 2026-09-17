<?php

return [
    /*
    | Sincronização com o Google Calendar (calendário único da equipe via conta
    | de serviço). Desligado por padrão — só liga quando configurado no .env.
    */
    'enabled' => (bool) env('GOOGLE_CALENDAR_SYNC', true),

    // ID do calendário da equipe (Configurações do calendário → "ID do calendário").
    'calendar_id' => env('GOOGLE_CALENDAR_ID', 'fourline.servicos@gmail.com'),

    // Caminho do arquivo JSON da chave da conta de serviço (fora da raiz web).
    'key_path' => env('GOOGLE_CALENDAR_KEY_PATH', storage_path('app/google-service-account.json')),

    // Fuso usado nos eventos.
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
];
