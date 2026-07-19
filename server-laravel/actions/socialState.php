<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $data = Live::overlayAccount(Live::template($action), $userId);

    if ($action === 'getStreams') {
        $data['all_loaded'] = true;
        $data['private_conversations'] = [];
    } elseif ($action === 'getStreamMessages') {
        $data['stream_id'] = (int)($params['stream_id'] ?? 0);
        $data['all_loaded'] = true;
        foreach (['private_system_messages', 'private_messages', 'guild_messages'] as $key) {
            if (array_key_exists($key, $data)) $data[$key] = [];
        }
    } elseif ($action === 'getIgnoredMessageCharacters') {
        $rows = Db::rows(
            'SELECT c.id, c.name, c.level, c.gender
               FROM `message_ignored_characters` i
               JOIN `character` c ON c.id = i.ignored_character_id
              WHERE i.character_id = ? ORDER BY i.id',
            [$char->id()]
        );
        $data['messages_ignored_character_info'] = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'character_id' => (int)$row['id'],
            'server_id' => 'local',
            'name' => (string)$row['name'],
            'level' => (int)$row['level'],
            'gender' => (string)$row['gender'],
        ], $rows);
    }

    $data['server_time'] = time();
    $data['time_correction'] = 0;
    return $data;
};
