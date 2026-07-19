<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Constantes e verificacao do protocolo, extraidas do cliente (build 252).
 * Ver docs/PROTOCOL.md.
 */
final class Protocol
{
    /** Salt secreto embutido no cliente: getRequestSignature = md5(action + SALT + user_id). */
    public const SALT = 'GN1al351';

    /** build_number enviado pelo cliente atual. */
    public const BUILD_NUMBER = 252;

    /** Auth is part of the wire contract and must never be silently ignored. */
    public static function strictAuth(): bool
    {
        $value = getenv('HZ_STRICT_AUTH');
        return $value === false || ($value !== '0' && strtolower((string)$value) !== 'false');
    }

    /** Recalcula a assinatura esperada. */
    public static function signature(string $action, string $userId): string
    {
        return md5($action . self::SALT . $userId);
    }

    /** Confere o campo `auth` enviado pelo cliente. */
    public static function verifyAuth(array $params): bool
    {
        $action = (string)($params['action'] ?? '');
        $userId = (string)($params['user_id'] ?? '0');
        $auth   = (string)($params['auth'] ?? '');
        return hash_equals(self::signature($action, $userId), $auth);
    }
}

/** Erro de jogo -> vira o campo "error" da resposta. */
class GameError extends \RuntimeException {}
