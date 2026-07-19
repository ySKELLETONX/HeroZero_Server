<?php
declare(strict_types=1);

/**
 * action: buyShopItem
 * Compra um item da loja real do personagem, debita moedas e equipa no slot alvo.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->buyShopItem((int)($params['item_id'] ?? 0), (int)($params['target_slot'] ?? 0));

    $boot = Live::template('autoLoginUser');
    return [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'inventory' => $char->inventoryData($boot['inventory'] ?? []),
        'items' => $char->itemsData($boot['items'][0] ?? []),
        'current_goal_values' => [
            'items_bought' => 1,
        ],
        'server_time' => time(),
        'time_correction' => 0,
    ];
};
