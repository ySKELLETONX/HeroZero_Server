<?php
declare(strict_types=1);

/**
 * action: checkForQuestComplete
 * Marca a missao ativa como concluida quando ts_complete ja passou.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $q = $char->checkActiveQuestComplete();
    $char = Character::load($char->id());

    $tpl = Live::template('checkForQuestComplete');
    $boot = Live::template('autoLoginUser');
    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        'quest' => [
            'id' => (int)$q['id'],
            'status' => (int)$q['status'],
            'fight_battle_id' => (int)$q['fight_battle_id'],
        ],
    ];
    $battle = $char->battleData((int)$q['fight_battle_id']);
    if ($battle !== null) {
        $data['battle'] = $battle;
    }
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    return $data;
};
