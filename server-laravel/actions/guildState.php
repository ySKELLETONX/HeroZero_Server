<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildChat;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? $params['existing_user_id'] ?? 0);
    $data = Live::accountState($userId);

    if (($params['action'] ?? '') === 'getGuildBattleList') {
        // O cliente itera data.guild_battle_entries (DOGuildBattleListEntry) e
        // ele mesmo remove a guild do proprio jogador da lista.
        $rows = Db::rows(
            'SELECT g.id, g.name, g.honor, g.artifact_ids,
                    g.emblem_background_shape, g.emblem_background_color,
                    g.emblem_background_border_color, g.emblem_icon_shape,
                    g.emblem_icon_color, g.emblem_icon_size,
                    COUNT(c.id) AS member_count,
                    COALESCE(ROUND(AVG(c.level)), 0) AS average_level
               FROM `guild` g
               LEFT JOIN `character` c ON c.guild_id = g.id
              WHERE g.status = 1
              GROUP BY g.id
              ORDER BY g.honor DESC'
        );
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'honor' => (int)$row['honor'],
                'member_count' => (int)$row['member_count'],
                'average_level' => (int)$row['average_level'],
                'artifact_ids' => (string)$row['artifact_ids'],
                'emblem_background_shape' => (int)$row['emblem_background_shape'],
                'emblem_background_color' => (int)$row['emblem_background_color'],
                'emblem_background_border_color' => (int)$row['emblem_background_border_color'],
                'emblem_icon_shape' => (int)$row['emblem_icon_shape'],
                'emblem_icon_color' => (int)$row['emblem_icon_color'],
                'emblem_icon_size' => (int)$row['emblem_icon_size'],
                'locale' => 'pt_BR',
            ];
        }
        $data['guild_battle_entries'] = $entries;
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuild') {
        // O cliente le data.requested_guild (DOGuild) e, se presente,
        // data.requested_guild_members (lista de DOGuildMember).
        $guildId = (int)($params['guild_id'] ?? 0);
        $guild = $guildId > 0 ? Db::row('SELECT * FROM `guild` WHERE id = ? LIMIT 1', [$guildId]) : null;
        if ($guild !== null) {
            $data['requested_guild'] = Live::shapeGuild($guild);
            if (!empty($params['refresh_members'])) {
                $data['requested_guild_members'] = Live::guildMembers((int)$guild['id']);
            }
        }
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuildList') {
        // Lista de guildas para o painel de "procurar guilda" (so as que aceitam membros).
        $rows = Db::rows(
            'SELECT g.id, g.name, g.description, g.honor, g.stat_guild_capacity,
                    g.min_apply_level, g.min_apply_honor, g.accept_members,
                    g.emblem_background_shape, g.emblem_background_color,
                    g.emblem_background_border_color, g.emblem_icon_shape,
                    g.emblem_icon_color, g.emblem_icon_size,
                    COUNT(c.id) AS member_count,
                    COALESCE(ROUND(AVG(c.level)), 0) AS average_level
               FROM `guild` g
               LEFT JOIN `character` c ON c.guild_id = g.id
              WHERE g.status = 1 AND g.accept_members = 1
              GROUP BY g.id
              ORDER BY g.honor DESC
              LIMIT 50'
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'description' => (string)$row['description'],
                'honor' => (int)$row['honor'],
                'member_count' => (int)$row['member_count'],
                'stat_guild_capacity' => (int)$row['stat_guild_capacity'],
                'average_level' => (int)$row['average_level'],
                'min_apply_level' => (int)$row['min_apply_level'],
                'min_apply_honor' => (int)$row['min_apply_honor'],
                'accept_members' => (bool)$row['accept_members'],
                'emblem_background_shape' => (int)$row['emblem_background_shape'],
                'emblem_background_color' => (int)$row['emblem_background_color'],
                'emblem_background_border_color' => (int)$row['emblem_background_border_color'],
                'emblem_icon_shape' => (int)$row['emblem_icon_shape'],
                'emblem_icon_color' => (int)$row['emblem_icon_color'],
                'emblem_icon_size' => (int)$row['emblem_icon_size'],
                'locale' => 'pt_BR',
                // Cliente crasha (DataObject: unknown field total_percentage) sem isso:
                // get_fullTotalPercentage() dos itens da lista de guildas le esta chave.
                // Capacidade preenchida (membros/vagas), 0-100.
                'total_percentage' => (int)$row['stat_guild_capacity'] > 0
                    ? min(100, (int)round(((int)$row['member_count'] / (int)$row['stat_guild_capacity']) * 100))
                    : 0,
            ];
        }
        $data['guild_entries'] = $list;
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuildBattleHistoryFights') {
        $char = Character::loadByUser($userId);
        $member = Db::row('SELECT guild_id FROM `character` WHERE id = ? LIMIT 1', [$char->id()]);
        $guildId = (int)($member['guild_id'] ?? 0);
        $rows = $guildId > 0 ? Db::rows(
            'SELECT id, status, battle_time, ts_attack, guild_attacker_id, guild_defender_id, guild_winner_id
               FROM `guild_battle`
              WHERE guild_attacker_id = ? OR guild_defender_id = ?
              ORDER BY id DESC LIMIT 30',
            [$guildId, $guildId]
        ) : [];
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'type' => 1,
                'status' => (int)$row['status'],
                'battle_time' => (int)$row['battle_time'],
                'ts_attack' => (int)$row['ts_attack'],
                'guild_attacker_id' => (int)$row['guild_attacker_id'],
                'guild_defender_id' => (int)$row['guild_defender_id'],
                'guild_winner_id' => (int)$row['guild_winner_id'],
            ];
        }
        $data['guild_battle_history_fights'] = $list;
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuildBattleHistoryFight') {
        $fightId = (int)($params['id'] ?? 0);
        $type = (int)($params['type'] ?? 1);
        $row = $type === 2
            ? Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id = ?', [$fightId])
            : Db::row('SELECT * FROM `guild_battle` WHERE id = ?', [$fightId]);
        if ($row === null) {
            throw new GameError('errGetGuildBattleHistoryFightNotFound');
        }
        $fight = $row;
        foreach ($fight as $k => $v) {
            if (is_numeric($v) && !in_array($k, ['attacker_character_ids', 'defender_character_ids', 'character_ids'], true)) {
                $fight[$k] = ctype_digit((string)$v) || (is_string($v) && preg_match('/^-?\d+$/', $v)) ? (int)$v : $v;
            }
        }
        $fight['id'] = $fightId;
        $fight['type'] = $type;
        $data['guild_battle_history_fight'] = $fight;
        return $data;
    }

    if (($params['action'] ?? '') !== 'getGuildLog') {
        return $data;
    }

    $char = Character::loadByUser($userId);
    $member = Db::row('SELECT guild_id, guild_rank FROM `character` WHERE id = ? LIMIT 1', [$char->id()]);
    $guildId = (int)($member['guild_id'] ?? 0);
    $rank = (int)($member['guild_rank'] ?? 3);
    if ($guildId <= 0) {
        throw new GameError('errSyncGuildNoGuild');
    }

    $rows = Db::rows(
        'SELECT id, guild_id, character_id, character_name, type, value1, value2, value3, timestamp
           FROM `guild_logs`
          WHERE guild_id = ?
          ORDER BY id ASC
          LIMIT 100',
        [$guildId]
    );
    $log = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $log[(string)$id] = [
            'id' => $id,
            'guild_id' => (int)$row['guild_id'],
            'character_id' => (int)$row['character_id'],
            'character_name' => (string)$row['character_name'],
            'type' => (int)$row['type'],
            'value1' => (string)$row['value1'],
            'value2' => (string)$row['value2'],
            'value3' => (string)$row['value3'],
            'timestamp' => (int)$row['timestamp'],
            'message' => '',
            'is_private' => false,
            'is_officer' => false,
            'read' => true,
        ];
    }

    // Mensagens de chat entram no mesmo guild_log: o cliente distingue evento
    // (tem "type") de mensagem (tem "message"). Ids de mensagem levam offset
    // p/ nao colidir com os de guild_logs (ver GuildChat).
    foreach (GuildChat::visibleMessages($guildId, $char->id(), $rank) as $entry) {
        $log[(string)$entry['id']] = $entry;
    }

    $data['guild_log'] = $log;
    // O cliente guarda chaves que comecam com "guild" e prefixa getguildlog_/syncgame_ sozinho.
    $data['sync_states'] = ['guild' . $guildId => GuildChat::version($guildId)];
    return $data;
};
