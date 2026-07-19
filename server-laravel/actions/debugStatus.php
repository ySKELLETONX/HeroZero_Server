<?php
declare(strict_types=1);

use HeroZero\Db;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $user = $userId > 0 ? Db::row(
        'SELECT u.id, u.email, u.premium_currency, u.session_id, c.id character_id, c.name, c.level, c.xp, c.game_currency
           FROM `user` u JOIN `character` c ON c.user_id = u.id
          WHERE u.id = ?',
        [$userId]
    ) : null;

    return [
        'debug_status' => [
            'ok' => true,
            'user' => $user,
            'server_time' => time(),
        ],
        'server_time' => time(),
        'time_correction' => 0,
    ];
};
