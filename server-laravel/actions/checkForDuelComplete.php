<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $duel = Db::row('SELECT * FROM `duel` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$char->id()]);
    if ($duel) {
        Db::exec('UPDATE `duel` SET character_a_status = 2 WHERE id = ?', [(int)$duel['id']]);
        $duel['character_a_status'] = 2;
    }
    $data = Live::template('checkForDuelComplete');
    if (isset($data['user'])) $data['user'] = $char->overlayUser($data['user']);
    if ($duel) $data['duel'] = Live::shapeLike($data['duel'] ?? [], $duel);
    return $data;
};
