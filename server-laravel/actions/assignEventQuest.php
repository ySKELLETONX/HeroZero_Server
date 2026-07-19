<?php
declare(strict_types=1);

/**
 * action: assignEventQuest  (aceitar a event quest ativa do catalogo global)
 * Entrada: event_quest_identifier
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $identifier = (string)($params['event_quest_identifier'] ?? '');

    $char = Character::loadByUser($userId);
    $char->assignEventQuest($identifier);
    return Live::accountState($userId);
};
