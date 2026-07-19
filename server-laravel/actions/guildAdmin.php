<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildChat;
use HeroZero\Live;

/**
 * Administracao de guilda: membros, patentes, convites, arena, taticas,
 * stats e chat. Patentes: 1 = lider, 2 = oficial, 3+ = membro.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    $me = Db::row('SELECT id, guild_id, guild_rank, name FROM `character` WHERE id = ?', [$cid]);
    $guildId = (int)($me['guild_id'] ?? 0);
    $myRank = (int)($me['guild_rank'] ?? 0);
    $isOfficer = $guildId > 0 && $myRank >= 1 && $myRank <= 2;
    $isLeader = $guildId > 0 && $myRank === 1;

    switch ($action) {
        case 'leaveGuild': {
            if ($guildId <= 0) break;
            Db::exec('UPDATE `character` SET guild_id = 0, guild_rank = 0, ts_guild_joined = 0 WHERE id = ?', [$cid]);
            $remaining = Db::rows('SELECT id, guild_rank FROM `character` WHERE guild_id = ? ORDER BY guild_rank ASC, ts_guild_joined ASC', [$guildId]);
            if ($remaining === []) {
                // ultimo membro saiu: guilda e desativada
                Db::exec('UPDATE `guild` SET status = 0 WHERE id = ?', [$guildId]);
            } elseif ($isLeader) {
                // lideranca passa pro membro mais antigo de maior patente
                $next = (int)$remaining[0]['id'];
                Db::exec('UPDATE `character` SET guild_rank = 1 WHERE id = ?', [$next]);
                Db::exec('UPDATE `guild` SET leader_character_id = ? WHERE id = ?', [$next, $guildId]);
            }
            break;
        }

        case 'kickGuildMember': {
            $targetId = (int)($params['character_id'] ?? $params['member_id'] ?? 0);
            if (!$isOfficer || $targetId <= 0 || $targetId === $cid) break;
            $target = Db::row('SELECT id, guild_id, guild_rank FROM `character` WHERE id = ?', [$targetId]);
            if ($target === null || (int)$target['guild_id'] !== $guildId) break;
            if ((int)$target['guild_rank'] <= $myRank) {
                throw new GameError('errGuildInsufficientRank');
            }
            Db::exec('UPDATE `character` SET guild_id = 0, guild_rank = 0, ts_guild_joined = 0 WHERE id = ?', [$targetId]);
            break;
        }

        case 'setGuildMemberRank': {
            $targetId = (int)($params['character_id'] ?? $params['member_id'] ?? 0);
            $rank = (int)($params['rank'] ?? $params['guild_rank'] ?? 3);
            if (!$isLeader || $targetId <= 0 || $rank < 2 || $rank > 4) break;
            Db::exec('UPDATE `character` SET guild_rank = ? WHERE id = ? AND guild_id = ?', [$rank, $targetId, $guildId]);
            break;
        }

        case 'inviteToGuild': {
            if (!$isOfficer) break;
            $targetId = (int)($params['character_id'] ?? 0);
            if ($targetId <= 0 && ($name = (string)($params['character_name'] ?? $params['name'] ?? '')) !== '') {
                $targetId = (int)(Db::value('SELECT id FROM `character` WHERE name = ?', [$name]) ?? 0);
            }
            if ($targetId <= 0) break;
            $exists = (int)Db::value('SELECT COUNT(*) FROM `guild_invites` WHERE guild_id = ? AND character_id = ?', [$guildId, $targetId]);
            if ($exists === 0) {
                Db::exec('INSERT INTO `guild_invites` (character_id, guild_id, ts_creation) VALUES (?, ?, ?)', [$targetId, $guildId, time()]);
            }
            break;
        }

        case 'declineGuildInvitation': {
            $fromGuild = (int)($params['guild_id'] ?? 0);
            Db::exec('DELETE FROM `guild_invites` WHERE character_id = ? AND (guild_id = ? OR ? = 0)', [$cid, $fromGuild, $fromGuild]);
            break;
        }

        case 'createGuildApplication': {
            // Sem tabela de candidaturas propria: guilda aberta = entra direto
            // (mesmo criterio do joinGuild); fechada = pedido ignorado com eco.
            $applyGuild = (int)($params['guild_id'] ?? 0);
            if ($guildId > 0 || $applyGuild <= 0) break;
            $guild = Db::row('SELECT * FROM `guild` WHERE id = ? AND status = 1', [$applyGuild]);
            if ($guild === null || !(bool)$guild['accept_members']) break;
            $memberCount = (int)Db::value('SELECT COUNT(*) FROM `character` WHERE guild_id = ?', [$applyGuild]);
            if ($memberCount >= (int)$guild['stat_guild_capacity']) break;
            $now = time();
            Db::exec('UPDATE `character` SET guild_id = ?, guild_rank = 3, ts_guild_joined = ?, ts_last_action = ? WHERE id = ?', [$applyGuild, $now, $now, $cid]);
            Db::exec("INSERT INTO `guild_logs` (guild_id, character_id, character_name, type, value1, value2, value3, timestamp)
                      VALUES (?, ?, ?, 2, '', '', '', ?)", [$applyGuild, $cid, $char->name(), $now]);
            break;
        }

        case 'setGuildApplySettings': {
            if (!$isOfficer) break;
            Db::exec('UPDATE `guild` SET min_apply_level = ?, min_apply_honor = ? WHERE id = ?', [
                max(0, (int)($params['min_apply_level'] ?? 0)),
                max(0, (int)($params['min_apply_honor'] ?? 0)),
                $guildId,
            ]);
            break;
        }

        case 'setGuildArena': {
            if (!$isOfficer) break;
            Db::exec('UPDATE `guild` SET arena_background = ? WHERE id = ?', [(int)($params['arena_background'] ?? $params['background'] ?? 0), $guildId]);
            break;
        }

        case 'setGuildBattleTactics': {
            if (!$isOfficer) break;
            Db::exec('UPDATE `guild` SET guild_battle_tactics_attack_order = ?, guild_battle_tactics_attack_tactic = ?,
                             guild_battle_tactics_defense_order = ?, guild_battle_tactics_defense_tactic = ?
                       WHERE id = ?', [
                (int)($params['attack_order'] ?? 0), (int)($params['attack_tactic'] ?? 0),
                (int)($params['defense_order'] ?? 0), (int)($params['defense_tactic'] ?? 0),
                $guildId,
            ]);
            break;
        }

        case 'improveGuildStat': {
            if ($guildId <= 0) break;
            $statType = (int)($params['stat_type'] ?? $params['type'] ?? 0);
            $column = match ($statType) {
                1 => 'stat_guild_capacity',
                2 => 'stat_character_base_stats_boost',
                3 => 'stat_quest_xp_reward_boost',
                4 => 'stat_quest_game_currency_reward_boost',
                default => null,
            };
            if ($column === null) break;
            // Modelo OFICIAL: melhorar stat custa moeda+premium do COFRE da guilda
            // (catalogos guild_stat_*_costs do constants do CDN, indexados pelo nivel
            // de destino). NAO existe "ponto de stat": o dialogo do client so trata
            // errRemoveGameCurrencyNotEnough / errRemovePremiumCurrencyNotEnough /
            // errImproveStatNoPermission -- QUALQUER outro erro (ex. o antigo
            // errGuildNotEnoughStatPoints) cai em n.reportError e derruba o jogo.
            if (!$isOfficer) {
                throw new GameError('errImproveStatNoPermission');
            }
            // game_currency_cost por nivel de destino (constants oficiais).
            // capacity: 10->30; boosts (tipos 2/3/4, tabelas identicas): 1->50.
            $gcCapacity = [11 => 400, 12 => 1200, 13 => 3000, 14 => 7000, 15 => 10000, 16 => 16000,
                17 => 22000, 18 => 30000, 19 => 36000, 20 => 42000, 21 => 50000, 22 => 56000,
                23 => 64000, 24 => 80000, 25 => 100000, 26 => 180000, 27 => 320000, 28 => 500000,
                29 => 700000, 30 => 820000];
            $gcBoost = [2 => 400, 3 => 600, 4 => 800, 5 => 1000, 6 => 1200, 7 => 1600, 8 => 2000,
                9 => 3000, 10 => 4000, 11 => 5000, 12 => 6000, 13 => 7000, 14 => 8000, 15 => 9000,
                16 => 10000, 17 => 12000, 18 => 14000, 19 => 16000, 20 => 18000, 21 => 20000,
                22 => 22000, 23 => 24000, 24 => 26000, 25 => 28000, 26 => 30000, 27 => 32000,
                28 => 34000, 29 => 36000, 30 => 38000, 31 => 40000, 32 => 42000, 33 => 44000,
                34 => 46000, 35 => 48000, 36 => 50000, 37 => 52000, 38 => 54000, 39 => 56000,
                40 => 58000, 41 => 60000, 42 => 64000, 43 => 68000, 44 => 72000, 45 => 76000,
                46 => 80000, 47 => 85000, 48 => 90000, 49 => 95000, 50 => 100000];
            // premium_currency_cost por nivel de destino.
            $pcCapacity = [15 => 5, 16 => 20, 17 => 35, 18 => 55, 19 => 70, 20 => 85, 21 => 105,
                22 => 120, 23 => 135, 24 => 150, 25 => 175, 26 => 200, 27 => 225, 28 => 250,
                29 => 275, 30 => 300];
            $pcBoost = [16 => 5, 17 => 10, 18 => 15, 19 => 20, 20 => 25, 21 => 30, 22 => 35,
                23 => 40, 24 => 45, 25 => 50, 26 => 55, 27 => 60, 28 => 65, 29 => 70, 30 => 75,
                31 => 80, 32 => 85, 33 => 90, 34 => 95, 35 => 100, 36 => 105, 37 => 110,
                38 => 115, 39 => 120, 40 => 125, 41 => 130, 42 => 135, 43 => 140, 44 => 145,
                45 => 150, 46 => 155, 47 => 160, 48 => 165, 49 => 170, 50 => 175];

            $g = Db::row("SELECT `{$column}` AS cur, game_currency, premium_currency FROM `guild` WHERE id = ? LIMIT 1", [$guildId]);
            if ($g === null) break;
            $next = (int)$g['cur'] + 1;
            $maxLevel = $statType === 1 ? 30 : 50;
            if ($next > $maxLevel) break; // ja no maximo -> no-op (client nem deveria pedir)
            $gcCost = ($statType === 1 ? $gcCapacity : $gcBoost)[$next] ?? 0;
            $pcCost = ($statType === 1 ? $pcCapacity : $pcBoost)[$next] ?? 0;
            if ((int)$g['game_currency'] < $gcCost) {
                throw new GameError('errRemoveGameCurrencyNotEnough');
            }
            if ((int)$g['premium_currency'] < $pcCost) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            Db::exec("UPDATE `guild`
                         SET `{$column}` = `{$column}` + 1,
                             game_currency = game_currency - ?,
                             premium_currency = premium_currency - ?
                       WHERE id = ? AND game_currency >= ? AND premium_currency >= ?",
                [$gcCost, $pcCost, $guildId, $gcCost, $pcCost]);
            break;
        }

        case 'deleteGuildChatMessage': {
            if (!$isOfficer) break;
            $msgId = (int)($params['message_id'] ?? $params['id'] ?? 0) - GuildChat::ID_OFFSET;
            if ($msgId > 0) {
                Db::exec('DELETE FROM `guild_messages` WHERE id = ? AND guild_id = ?', [$msgId, $guildId]);
            }
            break;
        }

        case 'sendGuildMassMail': {
            if (!$isOfficer) break;
            $subject = trim((string)($params['subject'] ?? ''));
            $message = trim((string)($params['message'] ?? $params['body'] ?? ''));
            if ($message === '') break;
            $ids = array_map(static fn(array $r): int => (int)$r['id'],
                Db::rows('SELECT id FROM `character` WHERE guild_id = ?', [$guildId]));
            Db::exec('INSERT INTO `messages` (character_from_id, character_to_ids, subject, message, flag, flag_value, ts_creation, readed)
                      VALUES (?, ?, ?, ?, 0, 0, ?, 0)', [$cid, implode(',', $ids), $subject, $message, time()]);
            break;
        }

        case 'initGuildLeaderVote': {
            if ($guildId <= 0) break;
            $candidate = (int)($params['character_id'] ?? $params['candidate_character_id'] ?? 0);
            if ($candidate > 0) {
                Db::exec('UPDATE `guild` SET pending_leader_vote_id = ? WHERE id = ?', [$candidate, $guildId]);
            }
            break;
        }

        case 'voteForGuildLeader': {
            if ($guildId <= 0) break;
            $candidate = (int)Db::value('SELECT pending_leader_vote_id FROM `guild` WHERE id = ?', [$guildId]);
            $accept = filter_var($params['vote'] ?? $params['accept'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if ($candidate > 0 && $accept) {
                $inGuild = (int)Db::value('SELECT COUNT(*) FROM `character` WHERE id = ? AND guild_id = ?', [$candidate, $guildId]);
                if ($inGuild === 1) {
                    Db::exec('UPDATE `character` SET guild_rank = 2 WHERE guild_id = ? AND guild_rank = 1', [$guildId]);
                    Db::exec('UPDATE `character` SET guild_rank = 1 WHERE id = ?', [$candidate]);
                    Db::exec('UPDATE `guild` SET leader_character_id = ?, pending_leader_vote_id = 0 WHERE id = ?', [$candidate, $guildId]);
                }
            } elseif (!$accept) {
                Db::exec('UPDATE `guild` SET pending_leader_vote_id = 0 WHERE id = ?', [$guildId]);
            }
            break;
        }

        // markGuildSynergyProcessAsSeen / upgradeGuildSynergy(-Storage) /
        // useGuildSynergyStorage / setNewGuildCompetitionViewed: sem colunas de
        // sinergia no schema — eco do accountState.
        default:
            break;
    }

    return Live::accountState($userId);
};
