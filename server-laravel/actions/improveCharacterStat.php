<?php
declare(strict_types=1);

/**
 * action: improveCharacterStat  (comprar +1 num atributo com game_currency)
 * Entrada: stat_type (1=stamina,2=strength,3=critical,4=dodge), skill_value
 * Efeito REAL no banco: debita moeda, incrementa o atributo, persiste.
 * Resposta: `character`/`user` vivos + current_goal_values (template).
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    $statType   = (int)($params['stat_type'] ?? 0);
    $skillValue = max(1, (int)($params['skill_value'] ?? 1));

    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->improveStat($statType, $skillValue);   // gratis primeiro; GameError se faltar moeda

    $data = Replay::data('improveCharacterStat');   // template (shape) + server_time
    if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
    if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);
    unset($data['trainings']);
    return Live::attachTrainingState($data, $char);
};
