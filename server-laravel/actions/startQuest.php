<?php
declare(strict_types=1);

/**
 * action: startQuest  (iniciar uma missao)
 * Entrada: quest_id
 * Efeito REAL no banco: marca a quest como iniciada (status=2, ts_complete futuro),
 * aponta character.active_quest_id e debita quest_energy.
 * Resposta segue o HAR (`user`, `character`, `quest` singular com status/ts_complete)
 * mas TAMBEM manda `quests` (plural): o cliente so aceita o merge do `quest` singular
 * se o id ja existir no mapa _quests (ver Character::activeQuest doc); sem o plural
 * o painel quest_progress pode crashar em get_duration() quando o id nao esta cacheado.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $questId = (int)($params['quest_id'] ?? 0);

    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->startQuest($questId);            // valida energia/posse; lanca GameError se invalido

    // Estado vivo apos iniciar (recarrega p/ refletir o UPDATE).
    $char = Character::load($char->id());

    $activeId = (int)$char->activeQuest()['id'];
    $tpl = Live::template('startQuest');
    $boot = Live::template('autoLoginUser');
    $tplQuest = $tpl['quests'][0] ?? $boot['quests'][0] ?? [];
    $quests = $char->questsData($tplQuest);

    $quest = $tplQuest;
    foreach ($quests as $q) {
        if ((int)$q['id'] === $activeId) { $quest = $q; break; }
    }

    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        // Objeto COMPLETO (nao so id/status/ts_complete): o painel quest_progress lia
        // duration/energy_cost/etc em cima do 'quest' parcial e crashava
        // (get_duration() em undefined) quando a missao vinha de um pool novo.
        'quest' => $quest,
        // O cliente so mescla 'quest' (singular) em cima de uma entrada JA existente
        // no mapa _quests (Y.prototype.refreshQuest: `if(!b.h.hasOwnProperty(c)) return null`).
        // Se o id nao estiver la (sessao antiga, pool regenerado etc.), active_quest_id
        // fica apontando pro vazio e o painel quest_progress crasha em get_duration().
        // Mandar 'quests' plural forca refreshQuests a reconstruir o mapa inteiro.
        'quests' => $quests,
    ];
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    if (!array_key_exists('server_time', $data)) $data['server_time'] = time();
    if (!array_key_exists('time_correction', $data)) $data['time_correction'] = 0;
    return $data;
};
