<?php

namespace App\Support;

/**
 * Geração CENTRALIZADA e segura de links de ação (tel/WhatsApp/Maps/Waze).
 * Nunca insere valor bruto do usuário em href — normaliza dígitos e usa
 * rawurlencode em destinos fixos. Retorna null quando o dado é insuficiente.
 */
class ClientLinks
{
    /** Mantém só os dígitos de um telefone. */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        return ($digits !== null && strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : null;
    }

    /**
     * Número no padrão internacional para WhatsApp (assume Brasil +55 quando o
     * DDI não veio). Ex.: "(61) 99999-9999" -> "5561999999999".
     */
    public static function whatsappNumber(?string $raw): ?string
    {
        $d = self::normalizePhone($raw);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '55') && strlen($d) >= 12) {
            return $d; // já tem DDI
        }
        if (strlen($d) === 10 || strlen($d) === 11) {
            return '55'.$d; // DDD + número nacional
        }

        return $d;
    }

    public static function telLink(?string $raw): ?string
    {
        $d = self::normalizePhone($raw);

        return $d ? 'tel:+'.$d : null;
    }

    public static function whatsappLink(?string $raw): ?string
    {
        $n = self::whatsappNumber($raw);

        return $n ? 'https://wa.me/'.$n : null;
    }

    public static function mapsLink(?string $endereco): ?string
    {
        $e = trim((string) $endereco);

        return $e !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($e) : null;
    }

    public static function wazeLink(?string $endereco): ?string
    {
        $e = trim((string) $endereco);

        return $e !== '' ? 'https://waze.com/ul?q='.rawurlencode($e).'&navigate=yes' : null;
    }

    /** Valida um CNPJ (dígitos verificadores). Aceita com ou sem máscara. */
    public static function isValidCnpj(?string $raw): bool
    {
        $c = preg_replace('/\D+/', '', (string) $raw);
        if ($c === null || strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }
        $calc = function (string $base, array $pesos): int {
            $soma = 0;
            foreach (str_split($base) as $i => $d) {
                $soma += (int) $d * $pesos[$i];
            }
            $resto = $soma % 11;

            return $resto < 2 ? 0 : 11 - $resto;
        };
        $d1 = $calc(substr($c, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $calc(substr($c, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $d1 === (int) $c[12] && $d2 === (int) $c[13];
    }
}
