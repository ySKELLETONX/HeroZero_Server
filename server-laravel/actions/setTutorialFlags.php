<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $incoming = json_decode((string)($params['flags'] ?? '{}'), true);
    if (!is_array($incoming)) $incoming = [];

    $current = Db::value('SELECT tutorial_flags FROM `character` WHERE id = ?', [$char->id()]);
    $flags = json_decode((string)$current, true);
    if (!is_array($flags)) $flags = [];
    $flags = array_replace($flags, $incoming);
    $flag = (string)($params['flag'] ?? '');
    if ($flag !== '') $flags[$flag] = true;

    Db::exec('UPDATE `character` SET tutorial_flags = ? WHERE id = ?', [json_encode($flags, JSON_UNESCAPED_SLASHES), $char->id()]);
    return Live::overlayAccount(Live::template('setTutorialFlags'), (int)$params['user_id']);
};
