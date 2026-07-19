<?php
declare(strict_types=1);

/**
 * Dungeon de historia (progressao de zona).
 * Ciclo: startNewStoryDungeonStep -> (startStoryDungeonBattle | instantFinishStoryDungeonStep)
 *        -> claimStoryDungeonStepReward (registra completed e destrava a proxima zona).
 * Estado em character.story_dungeon_state; a viagem em si e o setCharacterStage,
 * liberado pelo max_quest_stage que o claim aumenta.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);

    switch ($action) {
        case 'startNewStoryDungeonStep':
            $char->startStoryDungeonStep(
                (int)($params['story_dungeon_index'] ?? 0),
                (int)($params['step_index'] ?? 0)
            );
            break;

        case 'startStoryDungeonBattle':
            // Sem motor de batalha ainda: o ataque vence e completa o passo.
            $char->finishActiveStoryDungeonStep();
            break;

        case 'instantFinishStoryDungeonStep': {
            // Skip premium: 1 donut, padrao dos outros instant finish.
            $debited = Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - 1 WHERE id = ? AND premium_currency >= 1',
                [$userId]
            );
            if ($debited !== 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            $char->finishActiveStoryDungeonStep();
            break;
        }

        case 'claimStoryDungeonStepReward':
            $char->claimStoryDungeonStepReward();
            break;

        default:
            throw new GameError('errRequestInvalidParameter');
    }

    // accountState recarrega o character (max_quest_stage/moeda ja atualizados).
    return Live::accountState($userId);
};
