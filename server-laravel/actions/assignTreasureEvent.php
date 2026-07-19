<?php
declare(strict_types=1);

/**
 * action: assignTreasureEvent  (aceitar/iniciar a treasure event ativa do catalogo global)
 * Entrada: treasure_event_id
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 * Estado persistido em character.collected_item_pattern (blob da event) e
 * character.current_item_pattern_values (grid de celulas abertas).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $identifier = (string)($params['treasure_event_id'] ?? '');

    $char = Character::loadByUser($userId);
    $char->assignTreasureEvent($identifier);

    return Live::accountState($userId);
};
