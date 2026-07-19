<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $duration = max(3600, (int)($params['duration'] ?? 21600));
    $premiumCost = max(1, (int)ceil($duration / 21600));

    $char = Character::loadByUser($userId);
    $baseExpires = max(time(), (int)Db::value(
        'SELECT ts_active_sense_boost_expires FROM `character` WHERE id = ?',
        [$char->id()]
    ));
    $expires = $baseExpires + $duration;

    Db::pdo()->beginTransaction();
    try {
        $affected = Db::exec(
            'UPDATE `user`
                SET premium_currency = premium_currency - ?
              WHERE id = ? AND premium_currency >= ?',
            [$premiumCost, $userId, $premiumCost]
        );
        if ($affected !== 1) {
            Db::pdo()->rollBack();
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }

        Db::exec(
            'UPDATE `character`
                SET ts_active_sense_boost_expires = ?, ts_last_action = ?
              WHERE id = ?',
            [$expires, time(), $char->id()]
        );
        Db::pdo()->commit();
    } catch (GameError $e) {
        throw $e;
    } catch (Throwable $e) {
        if (Db::pdo()->inTransaction()) {
            Db::pdo()->rollBack();
        }
        throw $e;
    }

    return Live::accountState($userId);
};
