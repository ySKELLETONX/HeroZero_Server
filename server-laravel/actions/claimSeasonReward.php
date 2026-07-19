<?php
declare(strict_types=1);

/**
 * action: claimSeasonReward  (reivindicar uma recompensa da season pass)
 * Entrada: season_reward_id, discard_item
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $rewardId = (int)($params['season_reward_id'] ?? 0);

    $char = Character::loadByUser($userId);
    $char->claimSeasonReward($rewardId);

    return Live::accountState($userId);
};
