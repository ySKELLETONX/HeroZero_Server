<?php
declare(strict_types=1);

/**
 * action: createNextTreasureEventLevel  (avancar p/ o proximo nivel do grid da treasure event)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $char->createNextTreasureEventLevel();
    return Live::accountState($userId);
};
