<?php
declare(strict_types=1);

/**
 * action: buyTrainingEnergy  (recarregar sessoes de treino com moeda premium)
 * SEM captura real desta resposta no HAR; custo estimado (5 premium, flat) seguindo
 * o mesmo padrao de buySenseBooster.php. Devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $premiumCost = 5;

    $char = Character::loadByUser($userId);
    $char->buyTrainingEnergy($premiumCost);
    return Live::accountState($userId);
};
