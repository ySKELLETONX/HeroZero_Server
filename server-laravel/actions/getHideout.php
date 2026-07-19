<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $hideoutId = (int)($params['hideout_id'] ?? 0);
    if ($hideoutId <= 0) {
        $hideoutId = $char->id();
    }

    $now = time();
    $hideout = [
        'id' => $hideoutId,
        'hideout_points' => 0,
        'current_level' => 1,
        'idle_worker_count' => 1,
        'max_worker_count' => 1,
        'ts_time_worker_expires' => 0,
        'current_resource_glue' => 500,
        'max_resource_glue' => 1000,
        'current_resource_stone' => 500,
        'max_resource_stone' => 1000,
        'current_attacker_units' => 0,
        'max_attacker_units' => 5,
        'current_defender_units' => 0,
        'max_defender_units' => 5,
        'current_opponent_id' => 0,
        'current_opponent_server_id' => '',
        'current_opponent_chest_reward' => false,
        'ts_last_opponent_refresh' => 0,
        'active_battle_id' => 0,
        'ts_last_lost_attack' => 0,
        'current_worker_level' => 1,
        'current_barracks_level' => 1,
        'max_barracks_level' => 1,
        'current_gem_production_level' => 0,
        'current_broker_level' => 0,
        'current_robot_storage_level' => 1,
    ];
    for ($level = 0; $level < 5; $level++) {
        for ($slot = 0; $slot < 9; $slot++) {
            $hideout['room_slot_' . (($level * 10) + $slot)] = -1;
        }
    }

    // Salas reais do personagem (criadas na primeira visita e persistidas dai pra frente).
    $roomRows = $char->ensureHideoutRooms();
    $rooms = [];
    foreach ($roomRows as $r) {
        $rooms[] = [
            'id' => (int)$r['id'],
            'hideout_id' => $hideoutId,
            'identifier' => (string)$r['identifier'],
            'status' => (int)$r['status'],
            'level' => (int)$r['level'],
            'current_resource_amount' => (int)$r['current_resource_amount'],
            'max_resource_amount' => (int)$r['max_resource_amount'],
            'ts_last_resource_change' => (int)$r['ts_last_resource_change'],
            'ts_activity_end' => (int)$r['ts_activity_end'],
            'current_generator_level' => (int)$r['current_generator_level'],
            'additional_value_1' => (int)$r['slot'],
            'additional_value_2' => '',
            'guild_synergy_additional_value' => '',
        ];
        $hideout['room_slot_' . (int)$r['slot']] = (int)$r['id'];
        $hideout['current_level'] = max($hideout['current_level'], (int)$r['level']);
    }

    $boot = Live::template('autoLoginUser');
    return Live::attachTrainingState([
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'inventory' => $char->inventoryData($boot['inventory'] ?? []),
        'items' => $char->itemsData($boot['items'][0] ?? []),
        'trainings' => [$boot['trainings'][0] ?? []],
        'hideout' => $hideout,
        'hideout_rooms' => $rooms,
        'requested_hideout' => $hideout,
        'requested_hideout_rooms' => $rooms,
        'requested_hideout_optical_changes' => [],
        'server_time' => $now,
        'time_correction' => 0,
    ], $char);
};
