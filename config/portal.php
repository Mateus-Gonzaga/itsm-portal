<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mapeamento de perfis do GLPI → papéis do portal
    |--------------------------------------------------------------------------
    | Ao renomear um perfil no GLPI, ajuste AQUI (1 linha) — não precisa mexer
    | no código. Papéis válidos do portal: 'cliente', 'tecnico', 'gestor'.
    | Perfis não listados caem na regra por palavra-chave do mapRole (fallback).
    */
    'profile_roles' => [
        'Self-Service' => 'cliente',
        'Técnico FL'   => 'gestor',   // decisão: técnico atua como gestor no portal
        'Gestor'       => 'gestor',
        'Fourline'     => 'gestor',   // equipe interna
    ],

    /*
    | Perfis que o gestor PODE atribuir nas telas (anti-escalonamento).
    | Não inclua Super-Admin/Admin aqui.
    */
    'assignable_profiles' => ['Self-Service', 'Técnico FL', 'Gestor'],

    /*
    | Nome do perfil que identifica a "equipe técnica" na tela Técnicos.
    */
    'tecnico_profile' => 'Técnico FL',

    /*
    | Aviso por e-mail quando o portal dá erro (500). Vazio = MAIL_FROM_ADDRESS;
    | "off" desliga. O mesmo erro só é avisado 1x a cada 30 min.
    */
    'error_alert_email' => env('ERROR_ALERT_EMAIL', ''),

    /*
    | Endereço público do portal (usado no monitoramento do Zabbix).
    */
    'public_url' => env('PORTAL_PUBLIC_URL', env('APP_URL')),

    /*
    | Token da página /saude (lida pelo Zabbix). Vazio = página desligada (404).
    | Gere com: php -r "echo bin2hex(random_bytes(20));"
    */
    'health_token' => env('PORTAL_HEALTH_TOKEN', ''),
];
