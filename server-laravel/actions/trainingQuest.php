<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $action = (string)($params['action'] ?? '');
    $extra = [];

    if ($action === 'startTrainingQuest') {
        $extra['training_quest'] = $char->startTrainingQuest((int)($params['training_quest_id'] ?? 0));
    } elseif ($action === 'claimTrainingQuestRewards') {
        $extra['training_quest'] = $char->claimTrainingQuestRewards();
    } elseif ($action === 'claimTrainingStar') {
        $char->claimTrainingStar();
    }

    // accountState ja anexa trainings+training_quests consistentes (attachTrainingState).
    return Live::accountState($userId, $extra);
};
