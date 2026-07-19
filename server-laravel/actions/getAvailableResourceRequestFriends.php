<?php
declare(strict_types=1);

use HeroZero\Live;

return function (array $params): array {
    return Live::accountState((int)($params['user_id'] ?? 0), [
        'available_resource_request_friends' => [],
        'resource_request_friends' => [],
        'friends' => [],
    ]);
};
