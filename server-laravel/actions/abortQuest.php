<?php
declare(strict_types=1);

/**
 * action: abortQuest  (cancelar a missao ativa sem completar)
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState)
 * com a missao ja cancelada, igual ao padrao das demais actions de mutacao.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $char->abortQuest();
    return Live::accountState($userId);
};
