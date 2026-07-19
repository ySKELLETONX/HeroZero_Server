<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $duel = Db::row('SELECT * FROM `duel` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$char->id()]);
    if ($duel) {
        $rewards = json_decode((string)$duel['character_a_rewards'], true);
        if (!is_array($rewards)) $rewards = [];
        Db::exec(
            'UPDATE `character` SET game_currency = game_currency + ?, honor = honor + ?, active_duel_id = 0 WHERE id = ?',
            [(int)($rewards['coins'] ?? 0), (int)($rewards['honor'] ?? 0), $char->id()]
        );
        Db::exec('UPDATE `duel` SET character_a_status = 3 WHERE id = ?', [(int)$duel['id']]);
        $duel['character_a_status'] = 3;
    }
    $char = Character::load($char->id());
    $data = Live::template('claimDuelRewards');
    if (isset($data['user'])) $data['user'] = $char->overlayUser($data['user']);
    if (isset($data['character'])) {
        $data['character'] = $char->overlayCharacter($data['character']);
    }
    if ($duel) $data['duel'] = Live::shapeLike($data['duel'] ?? [], $duel);
    return $data;
};
