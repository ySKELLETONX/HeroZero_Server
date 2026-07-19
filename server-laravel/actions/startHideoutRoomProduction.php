<?php
declare(strict_types=1);

/**
 * action: startHideoutRoomProduction  (iniciar producao de recursos numa sala)
 * Entrada: hideoutRoomId, productionCount
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $roomId = (int)($params['hideoutRoomId'] ?? 0);
    $productionCount = max(1, (int)($params['productionCount'] ?? 1));

    $char = Character::loadByUser($userId);
    $char->startHideoutRoomProduction($roomId, $productionCount);

    return Live::accountState($userId);
};
