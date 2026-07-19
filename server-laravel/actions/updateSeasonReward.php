<?php
declare(strict_types=1);

/**
 * action: updateSeasonReward  (marcar recompensa da season como vista/atualizada)
 * Entrada: season_reward_id
 * SEM captura real desta resposta no HAR; sem semantica de mutacao conhecida (nao
 * concede nada, so confirma leitura), devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $char->ensureSeasonProgress();
    return Live::accountState($userId);
};
