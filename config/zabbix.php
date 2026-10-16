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

    // Problemas "ruído" (escondidos por padrão no Dashboards e fora das
    // contagens): trechos do nome separados por vírgula, sem diferenciar maiúsculas.
    'noise_patterns' => array_values(array_filter(array_map('trim', explode(',',
        (string) env('ZABBIX_NOISE', 'GoogleUpdater,edgeupdate,gupdate,MicrosoftEdgeElevationService')
    )))),

    // Segundos que as leituras do Zabbix ficam em cache (0 = sem cache).
    'cache_ttl' => (int) env('ZABBIX_CACHE_TTL', 30),
];
