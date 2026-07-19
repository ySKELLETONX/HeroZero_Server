<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;

/**
 * Esconderijo: operacoes sobre hideout_rooms alem de build/upgrade/producao.
 * A resposta e sempre o estado completo do hideout (mesmo shape do getHideout),
 * que ja e o que o painel do cliente consome apos cada operacao.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    $roomId = (int)($params['room_id'] ?? $params['hideout_room_id'] ?? $params['id'] ?? 0);

    switch ($action) {
        case 'placeHideoutRoom': {
            $slot = (int)($params['slot'] ?? $params['target_slot'] ?? -1);
            if ($roomId > 0 && $slot >= 0) {
                $occupied = Db::row('SELECT id FROM `hideout_rooms` WHERE character_id = ? AND slot = ? AND id <> ?', [$cid, $slot, $roomId]);
                if ($occupied === null) {
                    // sala guardada volta ativa ao ser posicionada
                    Db::exec('UPDATE `hideout_rooms` SET slot = ?, status = IF(status = 3, 0, status) WHERE id = ? AND character_id = ?', [$slot, $roomId, $cid]);
                }
            }
            break;
        }

        case 'storeHideoutRoom':
            if ($roomId > 0) {
                Db::exec('UPDATE `hideout_rooms` SET status = 3, ts_activity_end = 0 WHERE id = ? AND character_id = ?', [$roomId, $cid]);
            }
            break;

        case 'abortHideoutRoomStoring':
        case 'abortHideoutRoomUpgrading':
            if ($roomId > 0) {
                Db::exec('UPDATE `hideout_rooms` SET status = 0, ts_activity_end = 0 WHERE id = ? AND character_id = ?', [$roomId, $cid]);
            }
            break;

        // unlockHideoutRoomSlot / checkUnlockHideoutRoomSlotFinished /
        // instantFinishHideoutSlotUnlock / abortHideoutRoomSlotUnlocking /
        // startHideoutFight / refreshHideoutOpponent / blacksmith / gym /
        // exchange / item improvement / historico de batalhas: sem estado
        // persistido proprio — devolvem o estado atual do hideout.
        default:
            break;
    }

    $handler = require __DIR__ . '/getHideout.php';
    return $handler($params);
};
