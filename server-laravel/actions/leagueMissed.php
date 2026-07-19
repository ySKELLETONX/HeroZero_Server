<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Liga (espelha o fluxo de duelo na tabela league_fight) e duelos/lutas perdidas.
 *
 * Contrato do cliente (bundle html5_257):
 *  - league_opponents: lista de { opponent, opponent_inventory, opponent_inventory_items }
 *  - startLeagueFight: chaves league_fight + battle + opponent(+inventory/items)
 *  - missed_duels / missed_league_fights: inteiros (contadores)
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $leagueOpponents = static fn(int $cid): array => Live::leagueOpponents($cid);

    switch ($action) {
        case 'getLeagueOpponents':
        case 'refreshLeagueOpponents': {
            // Sorteia ANTES do accountState: a lista persiste em
            // character.league_opponents e o char ecoado ja sai com ela.
            $opponents = $leagueOpponents($cid);
            $data = Live::accountState($userId);
            $data['league_opponents'] = $opponents;
            return $data;
        }

        case 'startLeagueFight': {
            // Mesma trava do duelo: sem isso da pra empilhar lutas de liga (a
            // anterior fica orfa, active_league_fight_id sobrescrito). Codigo
            // errStartLeagueFightActiveLeagueFightFound ja existe no client.
            $activeFightId = (int)(Db::value('SELECT active_league_fight_id FROM `character` WHERE id = ?', [$cid]) ?? 0);
            if ($activeFightId !== 0) {
                throw new GameError('errStartLeagueFightActiveLeagueFightFound');
            }
            $opponentId = (int)($params['character_id'] ?? 0);
            if ($opponentId <= 0) {
                $opponentId = (int)(Db::value('SELECT id FROM `character` WHERE user_id = 0 AND id <> ? ORDER BY RAND() LIMIT 1', [$cid]) ?? 0);
            }
            $now = time();
            // Stats REAIS dos dois personagens, mesmo padrao do duelo (nao mais
            // um array fixo nivel-1).
            $profileA = $char->battleProfile('a');
            $profileB = Character::load($opponentId)->battleProfile('a');
            $profileB['profile'] = 'b';
            $rounds = ['rounds' => [['a' => 'a', 'd' => 'b', 'r' => 2, 'v' => 10], ['a' => 'b', 'd' => 'a', 'r' => 2, 'v' => 5], ['a' => 'a', 'd' => 'b', 'r' => 3, 'v' => 30]]];
            Db::exec("INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, 'a', ?)",
                [$now, json_encode($profileA, JSON_UNESCAPED_SLASHES), json_encode($profileB, JSON_UNESCAPED_SLASHES), json_encode($rounds, JSON_UNESCAPED_SLASHES)]);
            $battleId = (int)Db::pdo()->lastInsertId();
            Db::exec("INSERT INTO `league_fight` (ts_creation, battle_id, character_a_id, character_b_id, character_a_status, character_b_status, character_a_rewards, character_b_rewards, unread)
                      VALUES (?, ?, ?, ?, 1, 1, ?, ?, 'true')",
                [$now, $battleId, $cid, $opponentId, '{"coins":10,"league_points":15}', '{"coins":2,"league_points":-5}']);
            $fightId = (int)Db::pdo()->lastInsertId();
            Db::exec('UPDATE `character`
                         SET league_stamina = GREATEST(0, league_stamina - league_stamina_cost),
                             active_league_fight_id = ?, ts_last_league_fight = ?,
                             league_fight_count = league_fight_count + 1
                       WHERE id = ?', [$fightId, $now, $cid]);

            // Refresh ANTES do accountState (mesma razao do getLeagueOpponents).
            $refreshedOpponents = !empty($params['refresh_opponents']) ? $leagueOpponents($cid) : null;
            $data = Live::accountState($userId);
            $data['league_fight'] = Db::row('SELECT * FROM `league_fight` WHERE id = ?', [$fightId]) ?? [];
            $data['battle'] = Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]) ?? [];
            // Templates do boot: template raso ([]) deixaria o opponent sem
            // name/gender/level e a visualizacao da luta crasha (aviso do getCharacter.php).
            $boot = Live::template('autoLoginUser');
            $data['opponent'] = Live::requestedCharacter($opponentId, $boot['character'] ?? []);
            $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $boot['inventory'] ?? []);
            $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $boot['items'][0] ?? []);
            if ($refreshedOpponents !== null) {
                $data['league_opponents'] = $refreshedOpponents;
            }
            return $data;
        }

        case 'checkForLeagueFightComplete': {
            $fight = Db::row('SELECT * FROM `league_fight` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$cid]);
            $data = Live::accountState($userId);
            if ($fight) {
                Db::exec('UPDATE `league_fight` SET character_a_status = 2 WHERE id = ?', [(int)$fight['id']]);
                $fight['character_a_status'] = 2;
                $data['league_fight'] = $fight;
                $data['battle'] = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$fight['battle_id']]) ?? [];
            }
            return $data;
        }

        case 'claimLeagueFightRewards': {
            $fight = Db::row('SELECT * FROM `league_fight` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$cid]);
            if ($fight) {
                $rewards = json_decode((string)$fight['character_a_rewards'], true) ?: [];
                Db::exec('UPDATE `character`
                             SET game_currency = game_currency + ?,
                                 league_points = GREATEST(0, league_points + ?),
                                 active_league_fight_id = 0
                           WHERE id = ?',
                    [(int)($rewards['coins'] ?? 0), (int)($rewards['league_points'] ?? 0), $cid]);
                Db::exec('UPDATE `league_fight` SET character_a_status = 3 WHERE id = ?', [(int)$fight['id']]);
            }
            return Live::accountState($userId);
        }

        case 'deleteMissedDuels':
        case 'deleteAllMissedDuels':
            Db::exec('UPDATE `duel` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;

        case 'deleteMissedLeagueFights':
        case 'deleteAllMissedLeagueFights':
            Db::exec('UPDATE `league_fight` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;

        case 'claimMissedDuelsRewards': {
            $rows = Db::rows('SELECT * FROM `duel` WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            $coins = 0; $honor = 0;
            foreach ($rows as $d) {
                $r = json_decode((string)$d['character_b_rewards'], true) ?: [];
                $coins += (int)($r['coins'] ?? 0);
                $honor += (int)($r['honor'] ?? 0);
            }
            Db::exec('UPDATE `duel` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            Db::exec('UPDATE `character` SET game_currency = game_currency + ?, honor = GREATEST(0, honor + ?) WHERE id = ?', [$coins, $honor, $cid]);
            break;
        }

        case 'claimMissedLeagueFightsRewards': {
            $rows = Db::rows('SELECT * FROM `league_fight` WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            $coins = 0; $points = 0;
            foreach ($rows as $f) {
                $r = json_decode((string)$f['character_b_rewards'], true) ?: [];
                $coins += (int)($r['coins'] ?? 0);
                $points += (int)($r['league_points'] ?? 0);
            }
            Db::exec('UPDATE `league_fight` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            Db::exec('UPDATE `character` SET game_currency = game_currency + ?, league_points = GREATEST(0, league_points + ?) WHERE id = ?', [$coins, $points, $cid]);
            break;
        }

        // getMissedDuel / getMissedDuelsNew / getMissedLeagueFight(s): os
        // contadores missed_* ja saem no character do accountState; a lista
        // detalhada nao e persistida em formato proprio — eco.
        default:
            break;
    }

    return Live::accountState($userId);
};
