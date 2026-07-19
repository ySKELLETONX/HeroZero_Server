<?php
declare(strict_types=1);

/**
 * action: activateSeason  (ativar a season pass ativa, cria season_progress se preciso)
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
