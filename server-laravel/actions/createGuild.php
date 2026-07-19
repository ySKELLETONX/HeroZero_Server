<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? $params['existing_user_id'] ?? 0);
    $char = Character::loadByUser($userId);

    $existing = Live::guildForUser($userId);
    if ($existing !== null) {
        return Live::accountState($userId);
    }

    $name = trim((string)($params['name'] ?? ''));
    $description = trim((string)($params['description'] ?? ''));
    if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 64) {
        throw new GameError('errCreateInvalidName');
    }
    if (mb_strlen($description) > 2048) {
        throw new GameError('errCreateInvalidGuildNameOrDescription');
    }

    $duplicate = Db::value('SELECT id FROM `guild` WHERE LOWER(name) = LOWER(?) LIMIT 1', [$name]);
    if ($duplicate !== null) {
        throw new GameError('errCreateNameAlreadyExists');
    }

    $now = time();
    $acceptMembers = filter_var($params['accept_members'] ?? true, FILTER_VALIDATE_BOOLEAN);

    Db::exec(
        "INSERT INTO `guild`
            (ts_creation, initiator_character_id, leader_character_id, name, description, note, forum_page,
             premium_currency, game_currency, status, accept_members, honor, artifact_ids, missiles, auto_joins,
             battles_attacked, battles_defended, battles_won, battles_lost,
             artifacts_won, artifacts_lost, artifacts_owned_current, ts_last_artifact_released,
             missiles_fired, auto_joins_used, dungeon_battles_fought, dungeon_battles_won,
             stat_points_available, stat_guild_capacity, stat_character_base_stats_boost,
             stat_quest_xp_reward_boost, stat_quest_game_currency_reward_boost,
             arena_background, emblem_background_shape, emblem_background_color, emblem_background_border_color,
             emblem_icon_shape, emblem_icon_color, emblem_icon_size,
             use_missiles_attack, use_missiles_defense, use_missiles_dungeon,
             use_auto_joins_attack, use_auto_joins_defense, use_auto_joins_dungeon,
             pending_leader_vote_id, min_apply_level, min_apply_honor,
             guild_battle_tactics_attack_order, guild_battle_tactics_attack_tactic,
             guild_battle_tactics_defense_order, guild_battle_tactics_defense_tactic,
             active_training_booster_id, ts_active_training_boost_expires,
             active_quest_booster_id, ts_active_quest_boost_expires,
             active_duel_booster_id, ts_active_duel_boost_expires)
         VALUES
            (?, ?, ?, ?, ?, '', '',
             0, 500, 1, ?, 1000, '[]', 15, 0,
             0, 0, 0, 0,
             0, 0, 0, 0,
             0, 0, 0, 0,
             0, 10, 1,
             1, 1,
             1, 1, 2, 0,
             1, 4, 100,
             1, 1, 1,
             1, 1, 1,
             0, 1, 0,
             1, 10,
             1, 10,
             '', 0,
             '', 0,
             '', 0)",
        [$now, $char->id(), $char->id(), $name, $description, $acceptMembers ? 1 : 0]
    );

    $guildId = (int)Db::pdo()->lastInsertId();
    Db::exec(
        'UPDATE `character`
            SET guild_id = ?, guild_rank = 1, ts_guild_joined = ?, ts_last_action = ?
          WHERE id = ?',
        [$guildId, $now, $now, $char->id()]
    );
    Db::exec(
        "INSERT INTO `guild_logs` (guild_id, character_id, character_name, type, value1, value2, value3, timestamp)
         VALUES (?, ?, ?, 1, ?, '', '', ?)",
        [$guildId, $char->id(), $char->name(), $name, $now]
    );

    return Live::accountState($userId);
};
