<?php
declare(strict_types=1);

use HeroZero\Live;

return function (array $params): array {
    return Live::accountState((int)($params['user_id'] ?? $params['existing_user_id'] ?? 0));
};
