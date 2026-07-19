<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Boosters alem dos ja cobertos por buyBooster/buySenseBooster.
 * So cobra premium quando ha efeito persistido (coluna correspondente);
 * guild/hideout booster nao tem coluna no nosso schema, entao nao cobra.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $spendPremium = static function (int $userId, int $cost): void {
        $affected = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $userId, $cost]
        );
        if ($affected !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    };

    switch ($action) {
        case 'buyLeagueBooster': {
            $boosterId = (string)($params['booster_id'] ?? $params['identifier'] ?? 'booster_league1');
            $duration = max(3600, (int)($params['duration'] ?? 345600));
            $spendPremium($userId, max(1, (int)ceil($duration / 86400)));
            $base = max(time(), (int)Db::value('SELECT ts_active_league_boost_expires FROM `character` WHERE id = ?', [$cid]));
            Db::exec('UPDATE `character`
                         SET active_league_booster_id = ?, ts_active_league_boost_expires = ?, ts_last_action = ?
                       WHERE id = ?', [$boosterId, $base + $duration, time(), $cid]);
            break;
        }

        case 'buyMultitaskingBooster':
        case 'unlockMultitaskingBooster': {
            $duration = (int)($params['duration'] ?? -1);
            $spendPremium($userId, 10);
            // duration -1 = permanente (10 anos e alem do horizonte do cliente)
            $expires = $duration > 0 ? time() + $duration : time() + 315360000;
            Db::exec('UPDATE `character` SET ts_active_multitasking_boost_expires = ?, ts_last_action = ? WHERE id = ?', [$expires, time(), $cid]);
            break;
        }

        case 'buyGuildBooster': {
            $guildId = (int)Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$cid]);
            if ($guildId <= 0) break;
            $boosterId = (string)($params['booster_id'] ?? $params['identifier'] ?? 'guild_booster_quest1');
            $duration = (int)($params['duration'] ?? 604800);
            // Custo oficial (Live::GUILD_BOOSTER_CATALOG), pago do COFRE da guilda:
            // variantes "1" custam moeda comum, "2" custam donuts doados.
            $costEntry = Live::boosterCost($boosterId, $duration);
            if ($costEntry === null) {
                throw new GameError('errRequestInvalidParameter');
            }
            [$isPremium, $cost] = $costEntry;
            $col = $isPremium ? 'premium_currency' : 'game_currency';
            $debited = Db::exec(
                "UPDATE `guild` SET `{$col}` = `{$col}` - ? WHERE id = ? AND `{$col}` >= ?",
                [$cost, $guildId, $cost]
            );
            if ($debited !== 1) {
                throw new GameError($isPremium ? 'errRemovePremiumCurrencyNotEnough' : 'errRemoveGameCurrencyNotEnough');
            }
            $pair = match (true) {
                str_contains($boosterId, 'training') => ['active_training_booster_id', 'ts_active_training_boost_expires'],
                str_contains($boosterId, 'duel') => ['active_duel_booster_id', 'ts_active_duel_boost_expires'],
                default => ['active_quest_booster_id', 'ts_active_quest_boost_expires'],
            };
            $base = max(time(), (int)Db::value("SELECT `{$pair[1]}` FROM `guild` WHERE id = ?", [$guildId]));
            Db::exec("UPDATE `guild` SET `{$pair[0]}` = ?, `{$pair[1]}` = ? WHERE id = ?", [$boosterId, $base + $duration, $guildId]);
            break;
        }

        // buyHideoutBooster: sem coluna persistida — eco.
        default:
            break;
    }

    return Live::accountState($userId);
};
