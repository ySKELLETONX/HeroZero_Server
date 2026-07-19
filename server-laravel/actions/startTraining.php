<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->startTraining((int)($params['training_id'] ?? $params['id'] ?? 0));

    $boot = Live::template('autoLoginUser');
    return Live::attachTrainingState([
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'trainings' => [$boot['trainings'][0] ?? []],
        'current_goal_values' => [
            'trainings_started' => 1,
        ],
        'server_time' => time(),
        'time_correction' => 0,
    ], $char);
};
