<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $userId = (int)($params['user_id'] ?? $params['existing_user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $member = Db::row(
        'SELECT guild_id, guild_rank FROM `character` WHERE id = ? LIMIT 1',
        [$char->id()]
    );
    $guildId = (int)($member['guild_id'] ?? 0);
    $rank = (int)($member['guild_rank'] ?? 3);
    if ($guildId <= 0) {
        throw new GameError('errSyncGuildNoGuild');
    }

    $requireOfficer = static function () use ($rank, $action): void {
        if ($rank > 2) {
            $map = [
                'renameGuild' => 'errRenameGuildNoPermission',
                'setGuildDescription' => 'errSetDescriptionNoPermission',
                'setGuildNote' => 'errSetNoteNoPermission',
                'setGuildOfficerNote' => 'errSetOfficerNoteNoPermission',
                'setGuildEmblem' => 'errSetEmblemNoPermission',
            ];
            throw new GameError($map[$action] ?? 'errUserNotAuthorized');
        }
    };
    $requireLeader = static function () use ($rank, $action): void {
        if ($rank !== 1) {
            throw new GameError($action === 'renameGuild' ? 'errRenameGuildNoPermission' : 'errUserNotAuthorized');
        }
    };
    $log = static function (int $type, string $v1 = '', string $v2 = '', string $v3 = '') use ($guildId, $char): void {
        Db::exec(
            'INSERT INTO `guild_logs` (guild_id, character_id, character_name, type, value1, value2, value3, timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$guildId, $char->id(), $char->name(), $type, mb_substr($v1, 0, 64), mb_substr($v2, 0, 64), mb_substr($v3, 0, 64), time()]
        );
    };

    switch ($action) {
        case 'renameGuild':
            $requireLeader();
            $name = trim((string)($params['name'] ?? ''));
            if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 64) {
                throw new GameError('errRenameGuildInvalidName');
            }
            $duplicate = Db::value('SELECT id FROM `guild` WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1', [$name, $guildId]);
            if ($duplicate !== null) {
                throw new GameError('errRenameGuildNameAlreadyExists');
            }
            Db::exec('UPDATE `guild` SET name = ? WHERE id = ?', [$name, $guildId]);
            $log(20, $name);
            break;

        case 'setGuildDescription':
            $requireOfficer();
            $description = mb_substr(trim((string)($params['description'] ?? '')), 0, 2048);
            $forumPage = mb_substr(trim((string)($params['forum_page'] ?? '')), 0, 128);
            Db::exec('UPDATE `guild` SET description = ?, forum_page = ? WHERE id = ?', [$description, $forumPage, $guildId]);
            $log(21);
            break;

        case 'setGuildNote':
            $requireOfficer();
            $note = mb_substr(trim((string)($params['note'] ?? '')), 0, 4096);
            Db::exec('UPDATE `guild` SET note = ? WHERE id = ?', [$note, $guildId]);
            $log(22);
            break;

        case 'setGuildOfficerNote':
            $requireOfficer();
            $note = mb_substr(trim((string)($params['note'] ?? $params['officer_note'] ?? '')), 0, 4096);
            // The current schema has no officer_note column; keep the action successful
            // and record it in the log so the client sync flow completes.
            $log(23, $note);
            break;

        case 'setGuildEmblem':
            $requireOfficer();
            $backgroundShape = max(1, (int)($params['background_shape'] ?? 1));
            $backgroundColor = max(0, (int)($params['background_color'] ?? 0));
            $backgroundBorderColor = max(0, (int)($params['background_border_color'] ?? 0));
            $iconShape = max(1, (int)($params['icon_shape'] ?? 1));
            $iconColor = max(0, (int)($params['icon_color'] ?? 0));
            $iconSize = min(200, max(50, (int)($params['icon_size'] ?? 100)));
            Db::exec(
                'UPDATE `guild`
                    SET emblem_background_shape = ?,
                        emblem_background_color = ?,
                        emblem_background_border_color = ?,
                        emblem_icon_shape = ?,
                        emblem_icon_color = ?,
                        emblem_icon_size = ?
                  WHERE id = ?',
                [$backgroundShape, $backgroundColor, $backgroundBorderColor, $iconShape, $iconColor, $iconSize, $guildId]
            );
            $log(24, (string)$backgroundShape, (string)$iconShape, (string)$iconSize);
            break;

        case 'setGuildAcceptMembers':
            $requireOfficer();
            $value = filter_var($params['value'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            Db::exec('UPDATE `guild` SET accept_members = ? WHERE id = ?', [$value, $guildId]);
            $log(25, (string)$value);
            break;

        case 'setGuildLocale':
            $requireLeader();
            // The DB schema in this local server has no locale column. The client accepts
            // the refreshed guild payload with the default locale from Live::shapeGuild().
            $log(26, mb_substr((string)($params['locale'] ?? 'pt_BR'), 0, 16));
            break;

        default:
            throw new GameError('errActionNotSupported');
    }

    return Live::accountState($userId);
};
