<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Envelope de resposta esperado pelo cliente:
 *   { "data": {...}, "error": "" }
 * O cliente exige que AMBOS os campos existam (JsonActionRemoteRequest.onRequestCompleted).
 */
final class Response
{
    public static function ok($data): string
    {
        // Coage campos presentes-mas-null ao container vazio esperado (kill do
        // crash null -> .iterator()); so mexe em quem ja esta na resposta.
        if (is_array($data)) {
            $data = ResponseSchema::normalize($data);
        }
        return json_encode(
            ['data' => $data ?? new \stdClass(), 'error' => ''],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function error(string $error): string
    {
        return json_encode(
            ['data' => new \stdClass(), 'error' => $error],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
