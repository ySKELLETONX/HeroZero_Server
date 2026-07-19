<?php
declare(strict_types=1);

/**
 * action: claimQuestRewards
 * Aplica coins/xp da missao concluida, limpa active_quest_id e gera novo pool.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->claimQuestRewards();
    $char = Character::load($char->id());

    $tpl = Live::template('claimQuestRewards');
    $boot = Live::template('autoLoginUser');
    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        'quests' => $char->questsData($tpl['quests'][0] ?? $boot['quests'][0] ?? []),
    ];
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    return $data;
};
