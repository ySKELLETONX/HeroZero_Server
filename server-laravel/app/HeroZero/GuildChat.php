<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Chat da guilda (tabela guild_messages) servido dentro do guild_log.
 *
 * O cliente trata cada entrada do guild_log como GuildChatEntry: se tem a
 * chave "type" e um evento de log; sem "type" e mensagem de chat (message,
 * character_from_*, is_private, is_officer). Como guild_logs e guild_messages
 * tem auto_increment proprios, as mensagens ganham um offset de id p/ nao
 * colidir com eventos no mapa/dedupe do cliente.
 */
final class GuildChat
{
    /** Offset somado ao id de guild_messages nas respostas. */
    public const ID_OFFSET = 1000000;

    /** Quantas mensagens recentes entram no guild_log. */
    private const WINDOW = 100;

    /** Linha do banco -> entrada de chat no formato do cliente. */
    public static function entry(array $row): array
    {
        return [
            'id' => self::ID_OFFSET + (int)$row['id'],
            'guild_id' => (int)$row['guild_id'],
            'character_from_id' => (int)$row['character_from_id'],
            'character_from_name' => (string)$row['character_from_name'],
            'character_to_id' => (int)$row['character_to_id'],
            'is_private' => (int)$row['is_private'],
            'is_officer' => (int)$row['is_officer'],
            'message' => (string)$row['message'],
            'timestamp' => (int)$row['timestamp'],
            'read' => true,
        ];
    }

    /**
     * Mensagens visiveis p/ um membro: publicas, de oficial (se tiver patente)
     * e sussurros que ele mandou ou recebeu.
     */
    public static function visibleMessages(int $guildId, int $characterId, int $rank): array
    {
        $officer = $rank <= 2 ? 1 : 0;
        $rows = Db::rows(
            'SELECT * FROM (
                SELECT id, guild_id, character_from_id, character_from_name,
                       character_to_id, is_officer, is_private, message, timestamp
                  FROM `guild_messages`
                 WHERE guild_id = ?
                   AND (is_officer = 0 OR ? = 1)
                   AND (is_private = 0 OR character_from_id = ? OR character_to_id = ?)
                 ORDER BY id DESC
                 LIMIT ' . self::WINDOW . '
             ) m ORDER BY m.id ASC',
            [$guildId, $officer, $characterId, $characterId]
        );
        return array_map([self::class, 'entry'], $rows);
    }

    /**
     * Versao do log p/ sync_states["guild{id}"]: muda sempre que entra um
     * evento OU uma mensagem nova (soma de maximos e monotonica).
     */
    public static function version(int $guildId): int
    {
        $maxLog = (int)Db::value('SELECT COALESCE(MAX(id), 0) FROM `guild_logs` WHERE guild_id = ?', [$guildId]);
        $maxMsg = (int)Db::value('SELECT COALESCE(MAX(id), 0) FROM `guild_messages` WHERE guild_id = ?', [$guildId]);
        return $maxLog + $maxMsg;
    }
}
