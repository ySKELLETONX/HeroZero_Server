<?php
declare(strict_types=1);

/**
 * action: joinGuild  (entrar em uma guilda existente que aceita membros)
 * Entrada: guild_id, invitation (bool)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $guildId = (int)($params['guild_id'] ?? 0);
    $char = Character::loadByUser($userId);

    if (Live::guildForUser($userId) !== null) {
        throw new GameError('errJoinGuildAlreadyInGuild');
    }

    $guild = Db::row('SELECT * FROM `guild` WHERE id = ? AND status = 1', [$guildId]);
    if ($guild === null) {
        throw new GameError('errJoinGuildNotFound');
    }
    $isInvited = (int)Db::value(
        'SELECT COUNT(*) FROM `guild_invites` WHERE guild_id = ? AND character_id = ?',
        [$guildId, $char->id()]
    ) > 0;
    if (!$guild['accept_members'] && !$isInvited) {
        throw new GameError('errJoinGuildNotAcceptingMembers');
    }
    $memberCount = (int)Db::value('SELECT COUNT(*) FROM `character` WHERE guild_id = ?', [$guildId]);
    if ($memberCount >= (int)$guild['stat_guild_capacity']) {
        throw new GameError('errJoinGuildFull');
    }

    $now = time();
    Db::exec(
        'UPDATE `character` SET guild_id = ?, guild_rank = 3, ts_guild_joined = ?, ts_last_action = ? WHERE id = ?',
        [$guildId, $now, $now, $char->id()]
    );
    Db::exec('DELETE FROM `guild_invites` WHERE guild_id = ? AND character_id = ?', [$guildId, $char->id()]);
    Db::exec(
        "INSERT INTO `guild_logs` (guild_id, character_id, character_name, type, value1, value2, value3, timestamp)
         VALUES (?, ?, ?, 2, '', '', '', ?)",
        [$guildId, $char->id(), $char->name(), $now]
    );

    return Live::accountState($userId);
};
