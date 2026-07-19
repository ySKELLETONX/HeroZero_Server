<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Cupons (redeemVoucher / redeemUserVouchers). `rewards` do voucher e um JSON
 * (coins, premium, quest_energy, training_sessions, ...) aplicado na conta;
 * o cliente le a chave `voucher_rewards` na resposta.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    $codes = preg_split('/[;,\s]+/', (string)($params['code'] ?? $params['codes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $totalRewards = [];
    $now = time();
    foreach ($codes as $code) {
        $voucher = Db::row('SELECT * FROM `vouchers` WHERE code = ? AND status = 1', [$code]);
        if ($voucher === null) {
            throw new GameError('errVoucherInvalid');
        }
        if ((int)$voucher['ts_start'] > 0 && $now < (int)$voucher['ts_start']) throw new GameError('errVoucherInvalid');
        if ((int)$voucher['ts_end'] > 0 && $now > (int)$voucher['ts_end']) throw new GameError('errVoucherExpired');
        if ((int)$voucher['uses_max'] > 0 && (int)$voucher['uses_current'] >= (int)$voucher['uses_max']) throw new GameError('errVoucherUsedUp');
        if ((int)$voucher['user_id'] > 0 && (int)$voucher['user_id'] !== $userId) throw new GameError('errVoucherInvalid');

        $inserted = Db::exec('INSERT IGNORE INTO `voucher_redemptions` (voucher_id, user_id, character_id, ts_redeemed) VALUES (?, ?, ?, ?)',
            [(int)$voucher['id'], $userId, $cid, $now]);
        if ($inserted !== 1) {
            throw new GameError('errVoucherAlreadyRedeemed');
        }
        Db::exec('UPDATE `vouchers` SET uses_current = uses_current + 1 WHERE id = ?', [(int)$voucher['id']]);

        $rewards = json_decode((string)$voucher['rewards'], true) ?: [];
        foreach ($rewards as $k => $v) {
            $totalRewards[$k] = (int)($totalRewards[$k] ?? 0) + (int)$v;
        }
    }

    if ((int)($totalRewards['coins'] ?? 0) > 0) {
        Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [(int)$totalRewards['coins'], $cid]);
    }
    if ((int)($totalRewards['premium'] ?? 0) > 0) {
        Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?', [(int)$totalRewards['premium'], $userId]);
    }
    if ((int)($totalRewards['quest_energy'] ?? 0) > 0) {
        Db::exec('UPDATE `character` SET quest_energy = LEAST(max_quest_energy, quest_energy + ?) WHERE id = ?', [(int)$totalRewards['quest_energy'], $cid]);
    }
    if ((int)($totalRewards['training_sessions'] ?? 0) > 0) {
        Db::exec('UPDATE `character` SET training_count = GREATEST(0, training_count - ?) WHERE id = ?', [(int)$totalRewards['training_sessions'], $cid]);
    }

    $data = Live::accountState($userId);
    $data['voucher_rewards'] = (object)$totalRewards;
    return $data;
};
