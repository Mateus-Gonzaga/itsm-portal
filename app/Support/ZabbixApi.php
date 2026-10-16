<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo da API JSON-RPC do Zabbix para os comandos de administração
 * (usa ZABBIX_API_URL / ZABBIX_USER / ZABBIX_PASSWORD do .env).
 */
class ZabbixApi
{
    private ?string $token = null;

    public function url(): string
    {
        return (string) config('zabbix.api.url');
    }

    public function call(string $method, array $params): array
    {
        $resp = Http::asJson()->timeout(20)
            ->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->post($this->url(), ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => 1]);

        $json = $resp->json();
        if (isset($json['error'])) {
            throw new RuntimeException($method.': '.($json['error']['data'] ?? $json['error']['message'] ?? 'erro'));
        }

        return (array) ($json['result'] ?? []);
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $resp = Http::asJson()->timeout(20)->post($this->url(), [
            'jsonrpc' => '2.0',
            'method' => 'user.login',
            'params' => ['username' => (string) config('zabbix.api.user'), 'password' => (string) config('zabbix.api.password')],
            'id' => 1,
        ]);
        $token = $resp->json('result');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Login no Zabbix falhou: '.json_encode($resp->json('error')));
        }

        return $this->token = $token;
    }
}
