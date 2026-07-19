<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $boosterId = (string)($params['booster_id'] ?? $params['identifier'] ?? $params['id'] ?? '');
    $boosterType = (int)($params['booster_type'] ?? $params['type'] ?? 0);
    $duration = (int)($params['duration'] ?? 345600);

    // Custo do catalogo OFICIAL (Live::BOOSTER_CATALOG, extraido do CDN): o dialogo
    // do client oferece exatamente esses pares id/duracao — inclusive moeda comum
    // (premium=false) nos boosters baratos. errRequestInvalidParameter e FATAL no
    // client, entao so cai nele para pedido realmente fora do catalogo.
    $costEntry = Live::boosterCost($boosterId, $duration);
    if ($costEntry === null) {
        throw new GameError('errRequestInvalidParameter');
    }
    [$isPremium, $cost] = $costEntry;
    // -1 = permanente: expiracao no futuro distante.
    $expires = $duration === -1 ? time() + 1576800000 : time() + $duration;

    if ($userId > 0) {
        $char = \HeroZero\Character::loadByUser($userId);
        if ($boosterType === 0) {
            $boosterType = match (true) {
                str_starts_with($boosterId, 'booster_quest') => 1,
                str_starts_with($boosterId, 'booster_stats') => 2,
                str_starts_with($boosterId, 'booster_work') => 3,
                str_starts_with($boosterId, 'booster_league') => 4,
                default => 2,
            };
        }
        $field = match ($boosterType) {
            1 => ['active_quest_booster_id', 'ts_active_quest_boost_expires'],
            2 => ['active_stats_booster_id', 'ts_active_stats_boost_expires'],
            3 => ['active_work_booster_id', 'ts_active_work_boost_expires'],
            4 => ['active_league_booster_id', 'ts_active_league_boost_expires'],
            default => ['active_stats_booster_id', 'ts_active_stats_boost_expires'],
        };
        // Debita ANTES de ativar (atomico via condicao no UPDATE). Boosters baratos
        // custam moeda comum do personagem; os fortes custam donuts do user.
        if ($isPremium) {
            $debited = Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$cost, $userId, $cost]
            );
            if ($debited !== 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
        } else {
            $debited = Db::exec(
                'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                [$cost, $char->id(), $cost]
            );
            if ($debited !== 1) {
                throw new GameError('errRemoveGameCurrencyNotEnough');
            }
        }
        Db::exec("UPDATE `character` SET `{$field[0]}` = ?, `{$field[1]}` = ? WHERE id = ?", [$boosterId, $expires, $char->id()]);
    }

    return Live::accountState($userId);
};
