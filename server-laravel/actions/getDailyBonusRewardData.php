<?php
declare(strict_types=1);

/**
 * action: getDailyBonusRewardData
 * Config de recompensas (daily_bonus_reward_data) e o lookup vem do template capturado;
 * `character` e `user` sao sobrescritos com o estado vivo do banco.
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    $data = Replay::data('getDailyBonusRewardData');   // template completo + server_time sincronizado
    try {
        $char = Character::loadByUser((int)($params['user_id'] ?? 0));
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
        if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);
        unset($data['trainings']);
        $data = Live::attachTrainingState($data, $char);
    } catch (\HeroZero\GameError $e) {
        // sem personagem no banco -> devolve o template puro (nao trava o boot).
    }
    return $data;
};
