<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $userId = (int)($params['user_id'] ?? 0);

    if ($action === 'getGoalItemRewards') {
        return Live::accountState($userId, ['goal_item_ids' => []]);
    }

    $characterId = (int)($params['character_id'] ?? 0);
    $boot = Live::template('autoLoginUser');
    $currentValues = Character::emptyLike($boot['current_goal_values'] ?? []);
    $collected = [];
    if ($characterId > 0) {
        $collected = Db::rows(
            'SELECT goal_name, milestone_value, collected_at FROM `collected_goals` WHERE character_id = ? ORDER BY collected_at',
            [$characterId]
        );
    }
    return Live::accountState($userId, [
        'requested_character_current_goal_values' => $currentValues,
        'requested_character_collected_goals' => $collected,
    ]);
};
