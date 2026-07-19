<?php
declare(strict_types=1);

/**
 * action: collectTreasureCellReward  (coletar a recompensa de uma celula ja aberta)
 * Entrada: level, x, y, discard_item
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $level = (int)($params['level'] ?? 1);
    $x = (int)($params['x'] ?? 0);
    $y = (int)($params['y'] ?? 0);

    $char = Character::loadByUser($userId);
    $char->collectTreasureCellReward($level, $x, $y);

    return Live::accountState($userId);
};
