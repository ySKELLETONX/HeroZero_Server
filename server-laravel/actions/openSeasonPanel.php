<?php
declare(strict_types=1);

/**
 * action: openSeasonPanel  (abrir o painel da season, garante progresso existente)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $char->ensureSeasonProgress();
    return Live::accountState($userId);
};
