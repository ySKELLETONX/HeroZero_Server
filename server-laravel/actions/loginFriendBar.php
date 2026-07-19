<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['existing_user_id'] ?? $params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $boot = Live::template('autoLoginUser');
    return [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'friend_data' => [[
            'user_id' => $userId,
            'character_id' => $char->id(),
            'character_name' => (string)$char->name(),
            'character_level' => (int)($char->overlayCharacter($boot['character'] ?? [])['level'] ?? 1),
            'character_online' => true,
            'image_hash' => '',
            'platform_user_id' => '',
            'platform_name' => '',
            'platform_image_url' => '',
            'platform_image_width' => 0,
            'platform_image_height' => 0,
            'is_friend' => true,
            'is_guild_member' => false,
        ]],
        'time_correction' => 0,
        'server_time' => time(),
    ];
};
