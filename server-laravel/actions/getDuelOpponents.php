<?php
declare(strict_types=1);

/**
 * action: getDuelOpponents
 * `character` do jogador vem do banco; `opponents` vem das linhas NPC (character user_id=0).
 */

use HeroZero\Character;
use HeroZero\Replay;

return function (array $params): array {
    $data    = Replay::data('getDuelOpponents');
    $tplOpp  = $data['opponents'][0] ?? [];
    try {
        $char = Character::loadByUser((int)($params['user_id'] ?? 0));
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
        $data['opponents'] = Character::duelOpponents($char->id(), $tplOpp);
    } catch (\HeroZero\GameError $e) {
        // sem personagem -> mantem template.
    }
    return $data;
};
