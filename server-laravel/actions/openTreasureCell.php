<?php
declare(strict_types=1);

/**
 * action: openTreasureCell  (abrir uma celula do grid da treasure event, revela tokens)
 * Entrada: level, x, y, premium
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $level = (int)($params['level'] ?? 1);
    $x = (int)($params['x'] ?? 0);
    $y = (int)($params['y'] ?? 0);
    $premium = filter_var($params['premium'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $char = Character::loadByUser($userId);
    $char->openTreasureCell($level, $x, $y, $premium);

    return Live::accountState($userId);
};
