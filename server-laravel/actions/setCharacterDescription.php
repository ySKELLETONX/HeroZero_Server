<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $sets = [];
    $vals = [];
    if (array_key_exists('description', $params)) {
        $sets[] = '`description` = ?';
        $vals[] = mb_substr((string)$params['description'], 0, 255);
    }
    if (array_key_exists('note', $params)) {
        $sets[] = '`note` = ?';
        $vals[] = mb_substr((string)$params['note'], 0, 512);
    }
    if ($sets) {
        $vals[] = $char->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
    }
    return Live::accountState((int)($params['user_id'] ?? 0));
};
