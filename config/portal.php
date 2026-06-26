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
];
