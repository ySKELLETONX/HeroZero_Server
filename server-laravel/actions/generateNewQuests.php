<?php
declare(strict_types=1);

/**
 * action: generateNewQuests
 * Botao "novas missoes" do painel de quests. Remove as quests nao iniciadas e
 * gera um pool novo (a ativa e preservada). O cliente so faz updateData(data),
 * entao devolvemos user/character/quests como em claimQuestRewards.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->regenerateQuests();
    $char = Character::load($char->id());

    $boot = Live::template('autoLoginUser');
    return [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'quests' => $char->questsData($boot['quests'][0] ?? []),
    ];
};
