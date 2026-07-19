<?php
declare(strict_types=1);

use HeroZero\Live;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $data = Live::overlayAccount(Live::template($action), Live::currentUserId($params));
    if (!isset($data['user']) && !isset($data['character']) && Live::currentUserId($params) > 0) {
        $boot = Live::template('autoLoginUser');
        $data += [
            'user' => $boot['user'] ?? [],
            'character' => $boot['character'] ?? [],
        ];
        $data = Live::overlayAccount($data, Live::currentUserId($params));
    }
    return $data;
};
