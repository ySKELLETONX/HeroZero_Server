<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    return Live::accountState((int)($params['user_id'] ?? 0), [
        'last_payment_confirmed' => true,
        'payment_id' => (int)($params['payment_id'] ?? 0),
    ]);
};
