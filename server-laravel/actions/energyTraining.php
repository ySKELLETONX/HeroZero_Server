<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Energia de quest, stamina de duelo/liga e extras de treino.
 * Custos em premium seguem o padrao dos handlers ja capturados (flat, barato)
 * quando o HAR nao tem a resposta real.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $spendPremium = static function (int $userId, int $cost): void {
        $affected = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $userId, $cost]
        );
        if ($affected !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    };

    switch ($action) {
        case 'buyQuestEnergy':
            $spendPremium($userId, 2);
            Db::exec('UPDATE `character`
                         SET quest_energy = LEAST(max_quest_energy, quest_energy + 50),
                             quest_energy_refill_amount_today = quest_energy_refill_amount_today + 1,
                             ts_last_action = ?
                       WHERE id = ?', [time(), $cid]);
            break;

        case 'buyDuelStamina':
            $spendPremium($userId, 2);
            Db::exec('UPDATE `character`
                         SET duel_stamina = max_duel_stamina,
                             ts_last_duel_stamina_change = ?, ts_last_action = ?
                       WHERE id = ?', [time(), time(), $cid]);
            break;

        case 'useEnergyStorage':
            Db::exec('UPDATE `character`
                         SET quest_energy = LEAST(max_quest_energy, quest_energy + current_energy_storage),
                             current_energy_storage = 0, ts_last_action = ?
                       WHERE id = ? AND current_energy_storage > 0', [time(), $cid]);
            break;

        case 'useTrainingStorage':
            Db::exec('UPDATE `character`
                         SET training_count = GREATEST(0, training_count - current_training_storage),
                             current_training_storage = 0, ts_last_action = ?
                       WHERE id = ? AND current_training_storage > 0', [time(), $cid]);
            break;

        case 'skipTrainingCooldown':
            $spendPremium($userId, 1);
            Db::exec('UPDATE `character` SET ts_last_training = 0, ts_last_action = ? WHERE id = ?', [time(), $cid]);
            break;

        case 'extendTrainingTime':
            $spendPremium($userId, 1);
            Db::exec('UPDATE `training` SET ts_complete = ts_complete + 900 WHERE character_id = ? AND status = 1', [$cid]);
            break;

        case 'buyTrainingSenseBooster': {
            $duration = max(3600, (int)($params['duration'] ?? 21600));
            $spendPremium($userId, max(1, (int)ceil($duration / 21600)));
            // O treino usa o mesmo campo de sense boost do personagem.
            $base = max(time(), (int)Db::value('SELECT ts_active_sense_boost_expires FROM `character` WHERE id = ?', [$cid]));
            Db::exec('UPDATE `character` SET ts_active_sense_boost_expires = ?, ts_last_action = ? WHERE id = ?', [$base + $duration, time(), $cid]);
            break;
        }

        case 'refreshQuestsAndTrainings':
            $spendPremium($userId, 1);
            $char->regenerateQuests();
            break;

        default:
            break;
    }

    return Live::accountState($userId);
};
