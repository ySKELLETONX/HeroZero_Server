<?php
declare(strict_types=1);

/**
 * action: buildHideoutRoom  (construir uma sala nova no hideout)
 * Entrada: identifier, slot, level (sempre 0 na captura -> nivel inicial)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState)
 * com a sala nova ja persistida.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $identifier = (string)($params['identifier'] ?? '');
    $slot = (int)($params['slot'] ?? 0);

    $char = Character::loadByUser($userId);
    $char->buildHideoutRoom($identifier, $slot);

    return Live::accountState($userId);
};
