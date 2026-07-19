<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

/**
 * Social alem do socialState: ignorar/desiginorar, amigos, streams e
 * conversas privadas (mensagens vivem na tabela `messages`).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    switch ($action) {
        case 'ignoreMessageCharacter': {
            $targetId = (int)($params['character_id'] ?? $params['ignored_character_id'] ?? 0);
            if ($targetId <= 0 && ($name = (string)($params['character_name'] ?? $params['name'] ?? '')) !== '') {
                $targetId = (int)(Db::value('SELECT id FROM `character` WHERE name = ?', [$name]) ?? 0);
            }
            if ($targetId > 0 && $targetId !== $cid) {
                Db::exec('INSERT IGNORE INTO `message_ignored_characters` (character_id, ignored_character_id, ts_creation) VALUES (?, ?, ?)',
                    [$cid, $targetId, time()]);
            }
            break;
        }

        case 'unignoreMessageCharacter': {
            $targetId = (int)($params['character_id'] ?? $params['ignored_character_id'] ?? 0);
            Db::exec('DELETE FROM `message_ignored_characters` WHERE character_id = ? AND ignored_character_id = ?', [$cid, $targetId]);
            break;
        }

        case 'removeFriend': {
            // friend_data no personagem guarda a lista de amigos (JSON/CSV)
            $targetId = (string)($params['friend_id'] ?? $params['character_id'] ?? '');
            $raw = (string)(Db::value('SELECT friend_data FROM `character` WHERE id = ?', [$cid]) ?? '');
            if ($targetId !== '' && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $decoded = array_values(array_filter($decoded, static fn($f) => (string)(is_array($f) ? ($f['id'] ?? '') : $f) !== $targetId));
                    Db::exec('UPDATE `character` SET friend_data = ? WHERE id = ?', [json_encode($decoded), $cid]);
                }
            }
            break;
        }

        case 'sendStreamMessage': {
            $message = trim((string)($params['message'] ?? ''));
            $to = (string)($params['character_id'] ?? $params['stream_id'] ?? '');
            if ($message !== '') {
                Db::exec("INSERT INTO `messages` (character_from_id, character_to_ids, subject, message, flag, flag_value, ts_creation, readed)
                          VALUES (?, ?, '', ?, 0, 0, ?, 0)", [$cid, $to, $message, time()]);
            }
            break;
        }

        case 'deleteStreamMessage': {
            $msgId = (int)($params['message_id'] ?? $params['id'] ?? 0);
            if ($msgId > 0) {
                Db::exec('DELETE FROM `messages` WHERE id = ? AND character_from_id = ?', [$msgId, $cid]);
            }
            break;
        }

        case 'dismissStream': {
            // marca como lidas as mensagens recebidas do stream
            Db::exec("UPDATE `messages` SET readed = 1 WHERE FIND_IN_SET(?, character_to_ids)", [(string)$cid]);
            break;
        }

        case 'deletePrivateSystemMessages':
        case 'markPrivateSystemMessageAsRead': {
            $msgId = (int)($params['message_id'] ?? $params['id'] ?? 0);
            if ($action === 'markPrivateSystemMessageAsRead' && $msgId > 0) {
                Db::exec('UPDATE `messages` SET readed = 1 WHERE id = ? AND FIND_IN_SET(?, character_to_ids)', [$msgId, (string)$cid]);
            } elseif ($action === 'deletePrivateSystemMessages') {
                Db::exec('DELETE FROM `messages` WHERE character_from_id = 0 AND FIND_IN_SET(?, character_to_ids)', [(string)$cid]);
            }
            break;
        }

        // joinPrivateConversation / leavePrivateConversation /
        // renamePrivateConversation / inviteCharacterToPrivateConversation /
        // declinePrivateConversationInvitation / syncPrivateConversation /
        // getPrivateSystemMessageItems / claimPrivateSystemMessageItems:
        // sem estado adicional persistido — eco do accountState.
        default:
            break;
    }

    return Live::accountState($userId);
};
