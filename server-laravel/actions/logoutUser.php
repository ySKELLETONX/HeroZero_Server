<?php
declare(strict_types=1);

return function (array $params): array {
    return [
        'server_time' => time(),
        'time_correction' => 0,
    ];
};
