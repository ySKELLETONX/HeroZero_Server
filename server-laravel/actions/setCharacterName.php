<?php
declare(strict_types=1);

/**
 * action: setCharacterName  (definir/trocar o nome do heroi)
 * Entrada: name
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState)
 * com o nome ja atualizado, igual ao padrao das demais actions de mutacao.
 */

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $name = (string)($params['name'] ?? '');
    if ($userId <= 0) {
        throw new GameError('errRequestInvalidParameter');
    }

    $char = Character::loadByUser($userId);
    $char->setName($name);

    return Live::accountState($userId);
};
