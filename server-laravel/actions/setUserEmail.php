<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $email = trim((string)($params['email'] ?? $params['new_email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new GameError('errSetUserEmailInvalidEmail');
    }
    Db::exec('UPDATE `user` SET email = ?, confirmed = 1, trusted = 1, network = "" WHERE id = ?', [$email, $userId]);
    return Live::accountState($userId);
};
