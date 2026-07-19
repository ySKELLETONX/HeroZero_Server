<?php
declare(strict_types=1);

/**
 * action: instantFinishQuest
 * O cliente nao manda quest_id: a missao ativa vem de character.active_quest_id.
 * No oficial isso zera o timer e devolve user/character/quest parciais.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $qBefore = $char->activeQuest();
    $cost = ((int)$qBefore['ts_complete'] > time()) ? 1 : 0;
    $char->instantFinishQuest($cost);
    $char = Character::load($char->id());
    $q = $char->activeQuest();

    $tpl = Live::template('instantFinishQuest');
    $boot = Live::template('autoLoginUser');
    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        'quest' => [
            'id' => (int)$q['id'],
            'ts_complete' => 0,
        ],
    ];
    if ($cost > 0) {
        $data['current_goal_values'] = [
            'donuts_spent' => [
                'value' => $cost,
                'current_value' => $cost,
            ],
        ];
    }
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    return $data;
};
