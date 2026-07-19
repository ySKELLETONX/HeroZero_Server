<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\GameError;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $premiumAmount = max(0, (int)($params['premium_amount'] ?? 0));
    $paymentId = (time() * 1000) + ($userId % 1000);

    if ((getenv('HZ_ENV') ?: 'development') === 'production' && getenv('HZ_ALLOW_FAKE_PAYMENTS') !== '1') {
        throw new GameError('errPaymentNotAvailable');
    }

    if ($userId > 0 && $premiumAmount > 0) {
        Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?', [$premiumAmount, $userId]);
    }

    $data = [
        'payment_id' => $paymentId,
        'payment_link' => 'http://localhost:8000/payment-success.html?payment_id=' . $paymentId,
        'server_time' => time(),
        'time_correction' => 0,
    ];

    if ($userId > 0) {
        try {
            $hideoutHandler = require __DIR__ . '/getHideout.php';
            $data += $hideoutHandler($params);
            $data['payment_id'] = $paymentId;
            $data['payment_link'] = 'http://localhost:8000/payment-success.html?payment_id=' . $paymentId;
        } catch (Throwable $e) {
            // Payment can still proceed even if optional account state cannot be enriched.
        }
    }

    return $data;
};
