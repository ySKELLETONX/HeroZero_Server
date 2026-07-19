<?php
declare(strict_types=1);

use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    return Live::withLocalConstants(Replay::data('initGame'));
};
