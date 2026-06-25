<?php

return [

    // 'api' (Zabbix real) ou 'fake' (demo). Em produção, apontar a URL/credenciais
    // para o servidor real (ex.: o 192.168.101.26) via .env.
    'driver' => env('ZABBIX_DRIVER', 'api'),

    'api' => [
        'url' => env('ZABBIX_API_URL', 'http://host.docker.internal:8081/api_jsonrpc.php'),
        'user' => env('ZABBIX_USER', 'Admin'),
        'password' => env('ZABBIX_PASSWORD', 'zabbix'),
    ],
];
