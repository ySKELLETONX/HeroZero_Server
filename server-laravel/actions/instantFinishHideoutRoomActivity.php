<?php
declare(strict_types=1);

/**
 * action: instantFinishHideoutRoomActivity  (pular a espera da atividade da sala, premium)
 * Entrada: hideout_room_id
 * SEM captura real desta resposta no HAR; custo estimado (2 premium, flat).
 * Devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $roomId = (int)($params['hideout_room_id'] ?? 0);
    $premiumCost = 2;

    $char = Character::loadByUser($userId);
    $char->instantFinishHideoutRoomActivity($roomId, $premiumCost);

    return Live::accountState($userId);
};
