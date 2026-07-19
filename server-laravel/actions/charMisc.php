<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

/**
 * Miscelanea do personagem: flags de aparencia, estagio, reskill e bonus
 * diario de login.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    switch ($action) {
        case 'setCharacterAppearanceFlag': {
            // unico flag de aparencia persistido: mostrar/esconder mascara
            $flag = (string)($params['flag'] ?? '');
            $value = filter_var($params['value'] ?? $params['show'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            if ($flag === '' || str_contains($flag, 'mask')) {
                Db::exec('UPDATE `character` SET show_mask = ? WHERE id = ?', [$value, $cid]);
            }
            break;
        }

        case 'setCharacterStage': {
            $stage = (int)($params['stage'] ?? $params['quest_stage'] ?? 0);
            // So viaja ate o max ja destravado (quem sobe max_quest_stage e o
            // claim da story dungeon); regenera o pool de quests do novo stage.
            $max = (int)Db::value('SELECT max_quest_stage FROM `character` WHERE id = ?', [$cid]);
            if ($stage > 0 && $stage <= $max && $stage !== (int)Db::value('SELECT current_quest_stage FROM `character` WHERE id = ?', [$cid])) {
                Db::exec('UPDATE `character` SET current_quest_stage = ? WHERE id = ?', [$stage, $cid]);
                $char = Character::loadByUser($userId);   // relê current_quest_stage
                $char->regenerateQuests();
            }
            break;
        }

        case 'reskillCharacterStats': {
            // devolve os pontos treinados/comprados como pontos livres
            $row = Db::row('SELECT stat_bought_stamina, stat_bought_strength, stat_bought_critical_rating, stat_bought_dodge_rating FROM `character` WHERE id = ?', [$cid]) ?? [];
            $refund = array_sum(array_map('intval', $row));
            Db::exec('UPDATE `character`
                         SET stat_points_available = stat_points_available + ?,
                             stat_bought_stamina = 0, stat_bought_strength = 0,
                             stat_bought_critical_rating = 0, stat_bought_dodge_rating = 0
                       WHERE id = ?', [$refund, $cid]);
            break;
        }

        case 'claimDailyLoginBonus': {
            $today = (int)(time() / 86400);
            $last = (int)Db::value('SELECT ts_last_daily_login_bonus FROM `character` WHERE id = ?', [$cid]);
            if ((int)($last / 86400) < $today) {
                Db::exec('UPDATE `character`
                             SET ts_last_daily_login_bonus = ?,
                                 daily_login_bonus_day = (daily_login_bonus_day % 7) + 1,
                                 game_currency = game_currency + 50 * level
                           WHERE id = ?', [time(), $cid]);
            }
            break;
        }

        // selectStorygoal / useTitle / getCharacterMaxSpendableAmount /
        // claimDailyBonusRewardReward / claimNewsRewards: sem persistencia
        // propria — eco do accountState.
        default:
            break;
    }

    return Live::accountState($userId);
};
