<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $map = [
        'gender' => 'gender',
        'skin_color' => 'appearance_skin_color',
        'hair_color' => 'appearance_hair_color',
        'hair_type' => 'appearance_hair_type',
        'head_type' => 'appearance_head_type',
        'eyes_type' => 'appearance_eyes_type',
        'eyebrows_type' => 'appearance_eyebrows_type',
        'nose_type' => 'appearance_nose_type',
        'mouth_type' => 'appearance_mouth_type',
        'facial_hair_type' => 'appearance_facial_hair_type',
        'decoration_type' => 'appearance_decoration_type',
        'mask' => 'show_mask',
    ];
    $sets = [];
    $vals = [];
    foreach ($map as $param => $col) {
        if (array_key_exists($param, $params)) {
            $sets[] = "`$col` = ?";
            $vals[] = $params[$param];
        }
    }
    if ($sets) {
        $vals[] = $char->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
    }
    $data = Live::template('setCharacterAppearance');
    $boot = Live::template('autoLoginUser');
    $data += [
        'user' => $boot['user'] ?? [],
        'character' => $boot['character'] ?? [],
    ];
    return Live::overlayAccount($data, (int)$params['user_id']);
};
