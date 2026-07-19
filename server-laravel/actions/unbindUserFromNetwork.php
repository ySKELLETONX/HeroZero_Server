<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    Db::exec('UPDATE `user` SET network = "", trusted = 1, confirmed = 1 WHERE id = ?', [$userId]);
    return Live::accountState($userId);
};
