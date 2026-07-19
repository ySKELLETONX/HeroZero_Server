<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $identifier = (string)($params['identifier'] ?? '');
    $value = (int)($params['value'] ?? 0);
    if ($identifier !== '' && $value > 0) {
        Db::exec(
            'INSERT IGNORE INTO `collected_goals` (character_id, goal_name, milestone_value, collected_at) VALUES (?, ?, ?, ?)',
            [$char->id(), $identifier, $value, time()]
        );
    }
    $data = Live::overlayAccount(Live::template('collectGoalReward'), (int)$params['user_id']);
    $data['collected_goals'] = Db::rows('SELECT goal_name, milestone_value, collected_at FROM `collected_goals` WHERE character_id = ?', [$char->id()]);
    return $data;
};
