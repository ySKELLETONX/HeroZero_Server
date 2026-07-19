<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Push em tempo real para o servidor de socket (socket-server/server.js).
 *
 * O socket so carrega uma "poke": manda ao cliente um {type} que ele traduz num
 * re-sync via request.php (syncGame / syncGameAndGuild / syncFriendBar — ver
 * docs/PROTOCOL.md, secao SocketTransportLayer). Best-effort: se o socket estiver
 * desligado ou HZ_SOCKET_PUSH_URL vazio, nao faz nada e o cliente cai no fallback
 * de polling. Nunca lanca — push nunca deve quebrar o request HTTP principal.
 */
final class SocketPush
{
    private const VALID = ['syncGame', 'syncGameAndGuild', 'syncFriendBar'];

    public static function toUser(int $userId, string $type): bool
    {
        if ($userId <= 0 || !in_array($type, self::VALID, true)) {
            return false;
        }
        $url = (string)(getenv('HZ_SOCKET_PUSH_URL') ?: '');
        if ($url === '') {
            return false;
        }
        $token = (string)(getenv('HZ_SOCKET_TOKEN') ?: 'local-dev-token');
        $body = json_encode(['user_id' => $userId, 'type' => $type], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 300, // best-effort: nao segura o request
            CURLOPT_CONNECTTIMEOUT_MS => 200,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Push-Token: ' . $token,
            ],
        ]);
        curl_exec($ch);
        $ok = curl_errno($ch) === 0 && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok;
    }

    /** Notifica varios users (ex.: todos os membros de uma guilda menos o autor). */
    public static function toUsers(array $userIds, string $type): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            self::toUser($uid, $type);
        }
    }
}
