<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $settings = (string)($params['settings'] ?? '');
    if ($settings !== '') {
        Db::exec('UPDATE `user` SET settings = ? WHERE id = ?', [$settings, (int)($params['user_id'] ?? 0)]);
    }
    $data = Live::template('setUserSettings');
    $boot = Live::template('autoLoginUser');
    $data += ['user' => $boot['user'] ?? [], 'character' => $boot['character'] ?? []];
    return Live::overlayAccount($data, (int)($params['user_id'] ?? 0));
};
