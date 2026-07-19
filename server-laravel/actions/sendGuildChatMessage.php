<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildChat;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? $params['existing_user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $member = Db::row(
        'SELECT guild_id, guild_rank FROM `character` WHERE id = ? LIMIT 1',
        [$char->id()]
    );
    $guildId = (int)($member['guild_id'] ?? 0);
    $rank = (int)($member['guild_rank'] ?? 3);
    if ($guildId <= 0) {
        throw new GameError('errCharacterNoGuild');
    }

    $message = mb_substr(trim((string)($params['message'] ?? '')), 0, 512);
    if ($message === '') {
        // O cliente ja bloqueia input vazio; devolve o estado sem eco de mensagem.
        return Live::accountState($userId);
    }

    $isOfficer = filter_var($params['officer_message'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($isOfficer && $rank > 2) {
        // O cliente responde a errAddRecordNoPermission com um syncGuild.
        throw new GameError('errAddRecordNoPermission');
    }

    // Sussurro: character_to_name preenchido -> precisa ser membro da guilda.
    $toName = trim((string)($params['character_to_name'] ?? ''));
    $toId = 0;
    if ($toName !== '') {
        $toId = (int)(Db::value(
            'SELECT id FROM `character` WHERE guild_id = ? AND LOWER(name) = LOWER(?) LIMIT 1',
            [$guildId, $toName]
        ) ?? 0);
        if ($toId <= 0) {
            // O cliente extrai o nome apos os 40 chars do codigo de erro.
            throw new GameError('errSendGuildChatMessageInvalidCharacter' . $toName);
        }
    }

    Db::exec(
        'INSERT INTO `guild_messages`
            (guild_id, character_from_id, character_from_name, character_to_id, is_officer, is_private, message, timestamp)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$guildId, $char->id(), $char->name(), $toId, $isOfficer ? 1 : 0, $toId > 0 ? 1 : 0, $message, time()]
    );
    $messageId = (int)Db::pdo()->lastInsertId();

    $data = Live::accountState($userId);
    $data['guild_chat_message'] = GuildChat::entry([
        'id' => $messageId,
        'guild_id' => $guildId,
        'character_from_id' => $char->id(),
        'character_from_name' => $char->name(),
        'character_to_id' => $toId,
        'is_officer' => $isOfficer ? 1 : 0,
        'is_private' => $toId > 0 ? 1 : 0,
        'message' => $message,
        'timestamp' => time(),
    ]);
    return $data;
};
