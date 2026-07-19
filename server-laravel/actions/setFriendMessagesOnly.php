<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $settings = json_decode((string)(Db::value('SELECT settings FROM `user` WHERE id = ?', [$userId]) ?? '{}'), true);
    if (!is_array($settings)) $settings = [];
    $settings['friend_messages_only'] = filter_var($params['friend_messages_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    Db::exec('UPDATE `user` SET settings = ? WHERE id = ?', [json_encode($settings, JSON_UNESCAPED_SLASHES), $userId]);
    return Live::accountState($userId);
};
