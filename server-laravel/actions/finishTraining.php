<?php
declare(strict_types=1);

/**
 * action: finishTraining  (finalizar instantaneamente o treino ativo, premium)
 * SEM captura real desta resposta no HAR; custo estimado (3 premium, flat).
 * Devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $premiumCost = 3;

    $char = Character::loadByUser($userId);
    $char->finishTraining($premiumCost);
    return Live::accountState($userId);
};
