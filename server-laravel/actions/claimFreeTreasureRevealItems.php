<?php
declare(strict_types=1);

/**
 * action: claimFreeTreasureRevealItems  (bonus unico de tokens gratis da treasure event)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $char->claimFreeTreasureRevealItems();
    return Live::accountState($userId);
};
