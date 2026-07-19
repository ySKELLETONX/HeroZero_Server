<?php
declare(strict_types=1);

/**
 * action: donateToGuild  (doar moeda de jogo/premium ao cofre da guilda)
 * Entrada: game_currency_amount, premium_currency_amount
 * SEM captura real desta resposta no HAR; devolve o boot completo (accountState).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $gameAmount = max(0, (int)($params['game_currency_amount'] ?? 0));
    $premiumAmount = max(0, (int)($params['premium_currency_amount'] ?? 0));
    if ($gameAmount === 0 && $premiumAmount === 0) {
        throw new GameError('errRequestInvalidParameter');
    }

    $char = Character::loadByUser($userId);
    $guild = Live::guildForUser($userId);
    if ($guild === null) {
        throw new GameError('errDonateToGuildNoGuild');
    }
    if ($gameAmount > $char->gameCurrency()) {
        throw new GameError('errRemoveGameCurrencyNotEnough');
    }
    if ($premiumAmount > $char->premiumCurrency()) {
        throw new GameError('errRemovePremiumCurrencyNotEnough');
    }

    $pdo = Db::pdo();
    $pdo->beginTransaction();
    try {
        if ($gameAmount > 0) {
            $affected = Db::exec(
                'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                [$gameAmount, $char->id(), $gameAmount]
            );
            if ($affected !== 1) throw new GameError('errRemoveGameCurrencyNotEnough');
            Db::exec(
                'UPDATE `character` SET guild_donated_game_currency = guild_donated_game_currency + ? WHERE id = ?',
                [$gameAmount, $char->id()]
            );
        }
        if ($premiumAmount > 0) {
            $affected = Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$premiumAmount, $userId, $premiumAmount]
            );
            if ($affected !== 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
            Db::exec(
                'UPDATE `character` SET guild_donated_premium_currency = guild_donated_premium_currency + ? WHERE id = ?',
                [$premiumAmount, $char->id()]
            );
        }
        Db::exec(
            'UPDATE `guild` SET game_currency = game_currency + ?, premium_currency = premium_currency + ? WHERE id = ?',
            [$gameAmount, $premiumAmount, (int)$guild['id']]
        );
        Db::exec(
            "INSERT INTO `guild_logs` (guild_id, character_id, character_name, type, value1, value2, value3, timestamp)
             VALUES (?, ?, ?, 3, ?, ?, '', ?)",
            [(int)$guild['id'], $char->id(), $char->name(), (string)$gameAmount, (string)$premiumAmount, time()]
        );
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return Live::accountState($userId);
};
