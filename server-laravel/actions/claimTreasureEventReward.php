<?php
declare(strict_types=1);

/**
 * action: claimTreasureEventReward  (reivindicar recompensa de marco de tokens)
 * Entrada: reward_index, discard_item
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $rewardIndex = (int)($params['reward_index'] ?? 0);

    $char = Character::loadByUser($userId);
    $char->claimTreasureEventReward($rewardIndex);

    return Live::accountState($userId);
};
