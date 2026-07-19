<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $to = (string)($params['to'] ?? '');
    $message = (string)($params['message'] ?? '');
    if ($message !== '') {
        Db::exec(
            "INSERT INTO `messages` (character_from_id, character_to_ids, subject, message, flag, flag_value, ts_creation, readed)
             VALUES (?, ?, '', ?, '', '', ?, 0)",
            [$char->id(), $to, $message, time()]
        );
    }
    $data = Live::template('createPrivateConversation');
    $boot = Live::template('autoLoginUser');
    $data += ['user' => $boot['user'] ?? [], 'character' => $boot['character'] ?? []];
    return Live::overlayAccount($data, (int)$params['user_id']);
};
